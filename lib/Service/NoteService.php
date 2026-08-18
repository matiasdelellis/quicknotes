<?php
/*
 * @copyright 2016-2026 Matias De lellis <mati86dl@gmail.com>
 *
 * @author 2016 Matias De lellis <mati86dl@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\QuickNotes\Service;

use OCA\QuickNotes\Db\Attach;
use OCA\QuickNotes\Db\AttachMapper;

use OCA\QuickNotes\Db\Color;
use OCA\QuickNotes\Db\ColorMapper;

use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Db\NoteMapper;

use OCA\QuickNotes\Db\NoteShare;

use OCA\QuickNotes\Db\NoteState;
use OCA\QuickNotes\Db\NoteStateMapper;

use OCA\QuickNotes\Db\NoteTag;
use OCA\QuickNotes\Db\NoteTagMapper;

use OCA\QuickNotes\Db\Tag;
use OCA\QuickNotes\Db\TagMapper;

use OCA\QuickNotes\Exception\ConflictException;
use OCA\QuickNotes\Exception\ForbiddenException;

use OCA\QuickNotes\Service\FileService;
use OCA\QuickNotes\Service\ReminderService;
use OCA\QuickNotes\Service\SettingsService;
use OCA\QuickNotes\Service\ShareService;

use OCP\AppFramework\Db\DoesNotExistException;

use OCP\IUserManager;

/**
 * The notes, as one particular user sees them.
 *
 * Every method takes the user id of whoever is asking, and there are two ways
 * of reaching a note by id:
 *
 * - `get()`, which finds a note the user may *see*: their own, or one that was
 *   shared with them.
 * - `getOwned()`, which only ever finds their own.
 *
 * The difference is the whole security model of sharing, so it is worth being
 * explicit about which one a new method wants. Anything that is not the note
 * itself — its colour, its attachments, trashing it, destroying it — belongs
 * to the owner and goes through `getOwned()`. Editing the title and the
 * content goes through `get()` plus a permission check. What is personal — the
 * pin, the tags, the reminder, and whether it is archived — is stored per user
 * and needs no more than read access to the note it hangs off.
 */
class NoteService {

	private $notemapper;
	private $notetagmapper;
	private $colormapper;
	private $noteStateMapper;
	private $attachMapper;
	private $tagmapper;
	private $fileService;
	private $settingsService;
	private $reminderService;
	private $shareService;

	/** @var IUserManager */
	private $userManager;

	public function __construct(NoteMapper      $notemapper,
	                            NoteTagMapper   $notetagmapper,
	                            NoteStateMapper $noteStateMapper,
	                            ColorMapper     $colormapper,
	                            AttachMapper    $attachMapper,
	                            TagMapper       $tagmapper,
	                            FileService     $fileService,
	                            SettingsService $settingsService,
	                            ReminderService $reminderService,
	                            ShareService    $shareService,
	                            IUserManager    $userManager)
	{
		$this->notemapper      = $notemapper;
		$this->notetagmapper   = $notetagmapper;
		$this->noteStateMapper = $noteStateMapper;
		$this->colormapper     = $colormapper;
		$this->attachMapper    = $attachMapper;
		$this->tagmapper       = $tagmapper;
		$this->fileService     = $fileService;
		$this->settingsService = $settingsService;
		$this->reminderService = $reminderService;
		$this->shareService    = $shareService;
		$this->userManager     = $userManager;
	}

	/**
	 * Every note the user can see: their own, plus the ones shared with them
	 * directly or through one of their groups.
	 *
	 * @return Note[]
	 */
	public function getAll(string $userId): array {
		$notes = $this->notemapper->findAll($userId);

		// Notes somebody else shared. A note the owner moved to the trash is
		// on its way out; showing it to the people it was shared with would
		// only offer them a note that is about to disappear.
		$sharedIds = $this->shareService->getSharedNoteIds($userId);
		if (count($sharedIds) > 0) {
			foreach ($this->notemapper->findByIds($sharedIds) as $sharedNote) {
				if ($sharedNote->getUserId() === $userId) {
					// Sharing a note with a group one is a member of reaches
					// oneself. It is already in the list above, and twice in
					// the grid is worse than a pointless share.
					continue;
				}
				if (!is_null($sharedNote->getDeletedAt())) {
					continue;
				}
				if (is_null($this->userManager->get($sharedNote->getUserId()))) {
					// The owner is gone. Their notes are not reachable any
					// more, and neither is a display name for them.
					continue;
				}
				$notes[] = $sharedNote;
			}
		}

		if (count($notes) === 0) {
			return [];
		}

		// Two queries for the whole list instead of two per note: every share
		// of every note, and everything this user made of any of them.
		$noteIds = array_map(function (Note $note) { return (int)$note->getId(); }, $notes);
		$sharesByNote = $this->shareService->getSharesForNotes($noteIds);
		$states = $this->noteStateMapper->findAllForUser($userId);

		foreach ($notes as $note) {
			$this->hydrate($userId, $note, $sharesByNote[(int)$note->getId()] ?? [], $states);
		}

		return $notes;
	}

	/**
	 * A note the user may see: their own, or one shared with them.
	 *
	 * @return Note|null null when there is no such note, or when it is none of
	 *         their business — the two are deliberately indistinguishable
	 */
	public function get(string $userId, int $id): ?Note {
		try {
			$note = $this->notemapper->findShared($id);
		} catch (DoesNotExistException $e) {
			return null;
		}

		if ($note->getUserId() !== $userId
		    && $this->shareService->getPermissions($userId, $note) === 0) {
			return null;
		}

		return $this->hydrate($userId, $note);
	}

	/**
	 * A note the user owns. Everything that is the owner's alone goes through
	 * here, so that a share can never reach it however generous it is.
	 */
	public function getOwned(string $userId, int $id): ?Note {
		try {
			$note = $this->notemapper->find($id, $userId);
		} catch (DoesNotExistException $e) {
			return null;
		}
		return $this->hydrate($userId, $note);
	}

	/**
	 * @param string $userId
	 * @param string $title
	 * @param string $content
	 * @param string $color optional color.
	 * @param bool   $isPinned optional if note must be pinned
	 * @param array  $sharedWith optional list of shares
	 * @param array  $tags optional list of tags
	 * @param array  $attachments optional list of attachments
	 */
	public function create(string $userId,
	                       string $title,
	                       string $content,
	                       ?string $color = null,
	                       bool   $isPinned = false,
	                       array  $sharedWith = [],
	                       array  $tags = [],
	                       array  $attachments = []): ?Note
	{
		if (is_null($color)) {
			$color = $this->settingsService->getColorForNewNotes();
		}

		// Get color or append it
		if ($this->colormapper->colorExists($color)) {
			$hcolor = $this->colormapper->findByColor($color);
		} else {
			$hcolor = new Color();
			$hcolor->setColor($color);
			$hcolor = $this->colormapper->insert($hcolor);
		}

		// Create note and insert it
		$note = new Note();

		$note->setTitle($title);
		$note->setContent($content);
		$note->setTimestamp(time());
		$note->setColorId($hcolor->id);
		$note->setUserId($userId);

		$newNote = $this->notemapper->insert($note);
		$noteId = (int)$newNote->getId();

		// The pin belongs to whoever is looking, not to the note.
		$this->noteStateMapper->setPinned($userId, $noteId, $isPinned);

		$this->syncTags($userId, $noteId, $tags);

		foreach ($attachments as $attach) {
			if (isset($attach['file_id'])
			    && !$this->attachMapper->fileAttachExists($userId, $noteId, $attach['file_id'])) {
				$hAttach = new Attach();
				$hAttach->setUserId($userId);
				$hAttach->setNoteId($noteId);
				$hAttach->setFileId($attach['file_id']);
				$hAttach->setCreatedAt(time());
				$this->attachMapper->insert($hAttach);
			}
		}

		// Shares of a brand new note used to be accepted and quietly dropped
		// ("TODO: Insert optional shares"). They are applied now.
		if (count($sharedWith) > 0) {
			$this->shareService->syncUserShares($userId, $newNote, $sharedWith);
		}

		return $this->hydrate($userId, $newNote);
	}

	/**
	 * Save a note.
	 *
	 * Title and content are what a share can grant; everything else in the
	 * payload is either the owner's alone or personal to the caller. A field
	 * left out (null) is left alone, so a client that only means to change one
	 * thing does not have to send — and risk overwriting — the rest.
	 *
	 * @param string $userId who is saving
	 * @param int    $id
	 * @param string $title
	 * @param string $content
	 * @param string|null $color owner only
	 * @param bool|null   $isPinned personal to the caller
	 * @param array|null  $tags personal to the caller
	 * @param array|null  $attachments owner only
	 * @param array|null  $sharedWith owner only, the pre-0.9.1 way of sharing
	 * @param string|null $ifMatch etag the caller last saw, to catch a note
	 *        that changed underneath them
	 *
	 * @throws ForbiddenException when the caller may see the note but not edit it
	 * @throws ConflictException when `$ifMatch` no longer matches
	 */
	public function update(string  $userId,
	                       int     $id,
	                       string  $title,
	                       string  $content,
	                       ?string $color = null,
	                       ?bool   $isPinned = null,
	                       ?array  $tags = null,
	                       ?array  $attachments = null,
	                       ?array  $sharedWith = null,
	                       ?string $ifMatch = null): ?Note
	{
		try {
			$note = $this->notemapper->findShared($id);
		} catch (DoesNotExistException $e) {
			return null;
		}

		$isOwner = $note->getUserId() === $userId;
		$permissions = $this->shareService->getPermissions($userId, $note);
		if ($permissions === 0) {
			return null;
		}
		if (($permissions & NoteShare::PERMISSION_UPDATE) === 0) {
			throw new ForbiddenException('This note is read only for you');
		}

		if (!is_null($ifMatch) && $ifMatch !== '' && $ifMatch !== $note->getEtag()) {
			throw new ConflictException($this->hydrate($userId, $note));
		}

		$oldcolorid = (int)$note->getColorId();
		$newcolorid = $oldcolorid;

		// The colour is a property of the note, and the note is the owner's.
		if ($isOwner && !is_null($color)) {
			if ($this->colormapper->colorExists($color)) {
				$hcolor = $this->colormapper->findByColor($color);
			} else {
				$hcolor = new Color();
				$hcolor->setColor($color);
				$hcolor = $this->colormapper->insert($hcolor);
			}
			$newcolorid = (int)$hcolor->getId();
		}

		// Attachments are not the owner's alone since 0.9.4: the app serves
		// every attachment from the storage of whoever attached it, so a
		// collaborator's file is as visible as anybody else's. syncAttachments
		// only ever touches the rows of the caller.
		if (!is_null($attachments)) {
			$this->syncAttachments($userId, $id, $attachments);
		}

		// The old whole-note way of sharing. The dialog no longer uses it.
		if ($isOwner && !is_null($sharedWith)) {
			$this->shareService->syncUserShares($userId, $note, $sharedWith);
		}

		// Personal to whoever is saving, shared note or not.
		if (!is_null($tags)) {
			$this->syncTags($userId, $id, $tags);
		}
		if (!is_null($isPinned)) {
			$this->noteStateMapper->setPinned($userId, $id, $isPinned);
		}

		$note->setTitle($title);
		$note->setContent($content);
		$note->setTimestamp(time());
		$note->setColorId($newcolorid);

		$newnote = $this->notemapper->update($note);

		// Remove the old colour if this was the last note using it.
		if (($oldcolorid !== $newcolorid) && (!$this->notemapper->colorIdCount($oldcolorid))) {
			$oldcolor = $this->colormapper->find($oldcolorid);
			$this->colormapper->delete($oldcolor);
		}

		// Purge orphan tags.
		$this->tagmapper->dropOld();

		return $this->hydrate($userId, $newnote);
	}

	/**
	 * Take a note out of the caller's active list.
	 *
	 * Personal since 0.9.3, like the pin and the reminder: archiving says
	 * where the note sits in *this* user's grid, not what the note is. Read
	 * access is therefore enough, and that is the point — somebody who was
	 * given a note through a group share cannot leave it, because the share is
	 * not theirs to drop, and archiving is how they get it out of their way.
	 *
	 * @param string $userId
	 * @param int $id
	 *
	 * @return Note|null
	 */
	public function archive(string $userId, int $id): ?Note {
		$note = $this->get($userId, $id);
		if (is_null($note))
			return null;

		$now = (new \DateTime('now', new \DateTimeZone('GMT')))->format('Y-m-d H:i:s');
		$this->noteStateMapper->setArchived($userId, $id, $now);

		// Reload so the response carries the new state, without poking the
		// setters of an entity that is about to be written again.
		return $this->get($userId, $id);
	}

	/**
	 * Soft-delete a note. The row is kept in the database so that a
	 * background process can purge it later.
	 *
	 * The owner's alone, unlike archiving: this is the note on its way out of
	 * existence for everybody, not out of one grid.
	 *
	 * @param string $userId
	 * @param int $id
	 *
	 * @return Note|null
	 */
	public function trash(string $userId, int $id): ?Note {
		$note = $this->getOwned($userId, $id);
		if (is_null($note))
			return null;

		$now = (new \DateTime('now', new \DateTimeZone('GMT')))->format('Y-m-d H:i:s');
		$this->notemapper->updateDeletedAt($id, $now);

		// A note in the trash is a note on its way out; a notification for
		// something that is about to stop existing is noise. And the note may
		// have been shared, so the reminders to withdraw are not only the
		// owner's — `findDueReminders()` already stops firing them all.
		$this->reminderService->dismissForNote($id);

		return $this->getOwned($userId, $id);
	}

	/**
	 * Restore a note from the trash by clearing `deleted_at`. Whoever had
	 * archived it still has it archived: the two say different things.
	 *
	 * @param string $userId
	 * @param int $id
	 *
	 * @return Note|null
	 */
	public function restore(string $userId, int $id): ?Note {
		$note = $this->getOwned($userId, $id);
		if (is_null($note))
			return null;

		$this->notemapper->updateDeletedAt($id, null);

		return $this->getOwned($userId, $id);
	}

	/**
	 * Bring a note back to the caller's active list. A note in the trash stays
	 * there: unarchiving is not restoring.
	 *
	 * @param string $userId
	 * @param int $id
	 *
	 * @return Note|null
	 */
	public function unarchive(string $userId, int $id): ?Note {
		$note = $this->get($userId, $id);
		if (is_null($note))
			return null;

		$this->noteStateMapper->setArchived($userId, $id, null);

		return $this->get($userId, $id);
	}

	/**
	 * Arm, move or cancel the reminder of the calling user on a note.
	 *
	 * Personal since 0.9.2: anybody who can see the note can be reminded of
	 * it, and nobody sees anybody else's date. Read access is enough — the
	 * reminder is a row of the caller's, and the note itself is not touched —
	 * so this goes through `get()` and not `getOwned()`.
	 *
	 * @param string $userId
	 * @param int $id
	 * @param string|null $reminderAt UTC 'Y-m-d H:i:s', null to cancel
	 *
	 * @throws \InvalidArgumentException on a date that makes no sense
	 */
	public function setReminder(string $userId, int $id, ?string $reminderAt): ?Note {
		$normalized = $this->reminderService->normalize($reminderAt);

		$note = $this->get($userId, $id);
		if (is_null($note))
			return null;

		// Whatever was pending is no longer what the user asked for.
		$this->reminderService->dismiss($userId, $id);

		$this->noteStateMapper->setReminder($userId, $id, $normalized);

		return $this->get($userId, $id);
	}

	/**
	 * Empty the trash of a user: destroy every note of theirs that is in it.
	 *
	 * The same door "Delete permanently" goes through, one note at a time, so
	 * the shares, tags, reminders, attachments and orphan colours of each go
	 * with it. Nothing anybody else owns is touched, whatever it was shared
	 * with the caller as.
	 *
	 * @return int how many notes were destroyed
	 */
	public function emptyTrash(string $userId): int {
		$destroyed = 0;
		foreach ($this->notemapper->findDeletedByUser($userId) as $note) {
			if ($this->destroy($userId, $note->getId())) {
				$destroyed++;
			}
		}
		return $destroyed;
	}

	/**
	 * Destroy a note and everything that hangs off it.
	 *
	 * Only the owner can: the note is theirs, and the people it was shared
	 * with leave it (`ShareService::leave()`) or archive it instead.
	 *
	 * @param string $userId
	 * @param int $id
	 *
	 * @return bool false when there was no such note of theirs to destroy,
	 *         so the caller can say so instead of answering a cheerful 200 to
	 *         a request that did nothing
	 */
	public function destroy(string $userId, int $id): bool {
		try {
			$note = $this->notemapper->find($id, $userId);
		} catch(DoesNotExistException $e) {
			return false;
		}
		$oldcolorid = $note->getColorId();

		$this->shareService->deleteForNote($id);

		// The row is about to go, so no notification about it may outlive it,
		// whoever armed one.
		$this->reminderService->dismissForNote($id);

		// Tags and pins of a shared note belong to whoever set them, so there
		// can be rows here of users other than the owner.
		$this->notetagmapper->deleteByNoteId($id);
		$this->noteStateMapper->deleteByNoteId($id);

		// Whoever attached them; the files themselves are left alone, they
		// belong to their users.
		$this->attachMapper->deleteByNoteId($id);

		$this->notemapper->delete($note);

		// Delete Color if necessary
		if (!$this->notemapper->colorIdCount($oldcolorid)) {
			$oldcolor = $this->colormapper->find($oldcolorid);
			$this->colormapper->delete($oldcolor);
		}

		$this->tagmapper->dropOld();

		return true;
	}

	/**
	 * Bring the tags of a note in line with a list, for one user.
	 *
	 * Tags were personal before any of this: `quicknotes_note_tags` carries
	 * the user that set them, so two people can organise the same shared note
	 * in their own way without seeing each other's labels.
	 */
	private function syncTags(string $userId, int $noteId, array $tags): void {
		$dbTags = $this->tagmapper->getTagsForNote($userId, $noteId);
		foreach ($dbTags as $dbTag) {
			$keep = false;
			foreach ($tags as $tag) {
				if (isset($tag['id']) && $dbTag->getId() === $tag['id']) {
					$keep = true;
					break;
				}
			}
			if (!$keep) {
				$hnotetag = $this->notetagmapper->findNoteTag($userId, $noteId, $dbTag->getId());
				$this->notetagmapper->delete($hnotetag);
			}
		}

		foreach ($tags as $tag) {
			if (!isset($tag['name'])) {
				continue;
			}
			if (!$this->tagmapper->tagExists($userId, $tag['name'])) {
				$htag = new Tag();
				$htag->setName($tag['name']);
				$htag->setUserId($userId);
				$htag = $this->tagmapper->insert($htag);
			} else {
				$htag = $this->tagmapper->getTag($userId, $tag['name']);
			}

			if (!$this->notetagmapper->noteTagExists($userId, $noteId, $htag->getId())) {
				$noteTag = new NoteTag();
				$noteTag->setNoteId($noteId);
				$noteTag->setTagId($htag->getId());
				$noteTag->setUserId($userId);
				$this->notetagmapper->insert($noteTag);
			}
		}
	}

	/**
	 * Bring the attachments of a note in line with a list, for one user.
	 *
	 * Scoped to the caller in both directions, and that is the whole trick to
	 * letting a collaborator attach anything at all: the editor round-trips
	 * the *whole* list of a note through the DOM, other people's attachments
	 * included, so without this a save would file somebody else's file as the
	 * caller's — a second row, pointing at a file they cannot even read — and
	 * a collaborator could drop the owner's attachments by saving.
	 *
	 * An entry with no `user_id` is one the caller has just attached and not
	 * saved yet, so it counts as theirs. Entries of anybody else are left
	 * exactly as they are.
	 */
	private function syncAttachments(string $userId, int $noteId, array $attachments): void {
		$mine = [];
		foreach ($attachments as $attach) {
			if (!isset($attach['file_id'])) {
				continue;
			}
			$owner = $attach['user_id'] ?? null;
			if (is_null($owner) || $owner === $userId) {
				$mine[] = (int)$attach['file_id'];
			}
		}

		foreach ($this->attachMapper->findFromNote($userId, $noteId) as $dbAttach) {
			if (!in_array((int)$dbAttach->getFileId(), $mine, true)) {
				$this->attachMapper->delete($dbAttach);
			}
		}

		foreach ($mine as $fileId) {
			if (!$this->attachMapper->fileAttachExists($userId, $noteId, $fileId)) {
				if (is_null($this->fileService->getFileOf($userId, $fileId))) {
					// You can only attach a file you can actually reach. A
					// client sending somebody else's file id as one of its own
					// would otherwise leave a row pointing at a file the app
					// could never serve on its behalf.
					continue;
				}

				$hAttach = new Attach();
				$hAttach->setUserId($userId);
				$hAttach->setNoteId($noteId);
				$hAttach->setFileId($fileId);
				$hAttach->setCreatedAt(time());
				$this->attachMapper->insert($hAttach);
			}
		}
	}

	/**
	 * Whether the person who attached something can still see the note.
	 *
	 * Free for the owner and for anybody already resolved this request: the
	 * group memberships behind it are cached per user in `ShareService`, so a
	 * whole listing costs one query per distinct outside attacher.
	 *
	 * @param NoteShare[] $shares every share of the note, already fetched
	 */
	private function canStillContribute(string $attacherId, Note $note, array $shares): bool {
		if ($attacherId === $note->getUserId()) {
			return true;
		}
		return $this->shareService->canSee($attacherId, $note, $shares);
	}

	/**
	 * What whoever is asking made of this note: the pin, the reminder and
	 * whether they archived it.
	 *
	 * All three live on the same row of `quicknotes_note_states`, so a single
	 * note costs a single lookup. A listing passes in the rows it already
	 * fetched in bulk for the whole list instead.
	 *
	 * @param array<int, NoteState>|null $states rows of this user, by note id
	 */
	private function applyPersonalState(string $userId, Note $note, ?array $states): void {
		$noteId = (int)$note->getId();

		if (is_null($states)) {
			try {
				$state = $this->noteStateMapper->find($userId, $noteId);
			} catch (DoesNotExistException $e) {
				$state = null;
			}
		} else {
			$state = $states[$noteId] ?? null;
		}

		$note->setIsPinned(!is_null($state) && $state->getPinned());
		$note->setArchivedAt(is_null($state) ? null : $state->getArchivedAt());
		$note->setReminderAt(is_null($state) ? null : $state->getReminderAt());
		$note->setReminderNotifiedAt(is_null($state) ? null : $state->getReminderNotifiedAt());
	}

	/**
	 * Fill in everything a client needs that is not a column of the note.
	 *
	 * All of it depends on who is asking: the tags and the pin are theirs, the
	 * permissions are what the shares grant them, and the list of shares is
	 * only shown to somebody who could act on it. Two users hydrating the same
	 * row get two different answers, which is why this takes the user id and
	 * why nothing here may be cached on the entity.
	 *
	 * `$shares` and `$states` let a listing pass in what it already fetched in
	 * bulk; on their own, both are looked up here.
	 *
	 * @param NoteShare[]|null $shares every share of this note
	 * @param array<int, NoteState>|null $states what this user made of the
	 *        notes being hydrated, keyed by note id
	 */
	private function hydrate(string  $userId,
	                         Note    $note,
	                         ?array  $shares = null,
	                         ?array  $states = null): Note {
		$noteId = (int)$note->getId();
		$isOwner = $note->getUserId() === $userId;

		if (is_null($shares)) {
			$shares = $this->shareService->getSharesForNote($noteId);
		}

		$note->setColor($this->colormapper->find($note->getColorId())->getColor());

		$this->applyPersonalState($userId, $note, $states);

		// Tags are per user, so these are the tags of whoever is asking — not
		// the owner's. A shared note starts out untagged for the recipient,
		// and whatever they tag it with stays theirs.
		$note->setTags($this->tagmapper->getTagsForNote($userId, $noteId));

		// Attachments. The file lives in the storage of whoever attached it,
		// and the app serves it to anybody who can see the note — so this no
		// longer asks whether the *viewer* can reach the file, which is what
		// used to make attachments vanish from a shared note without a trace.
		// The two Files links are the exception: they only mean something for
		// somebody who has the file in their own Files.
		$rAttachts = [];
		foreach ($this->attachMapper->findAllFromNote($noteId) as $attach) {
			$fileId = (int)$attach->getFileId();

			if (!$this->canStillContribute($attach->getUserId(), $note, $shares)) {
				// Whoever attached this is no longer part of the note — they
				// left a group it was shared with, say. Their file is not the
				// note's audience's to see any more. Being unshared personally
				// takes the row itself (`ShareService::forgetRecipientState`).
				continue;
			}

			$file = $this->fileService->getFileOf($attach->getUserId(), $fileId);
			if (is_null($file)) {
				// The file was deleted or moved out of reach of the person who
				// attached it. Nothing to show and nothing to link to.
				continue;
			}

			$attach->setIsMine($attach->getUserId() === $userId);
			$attach->setBasename($file->getName());
			$attach->setMime($file->getMimetype());
			$attach->setHasPreview($this->fileService->hasPreview($file));

			$attach->setPreviewUrl($this->fileService->getAttachmentPreviewUrl($noteId, $fileId, 512));
			$attach->setDownloadUrl($this->fileService->getAttachmentDownloadUrl($noteId, $fileId));
			$attach->setRedirectUrl($this->fileService->getRedirectToFileUrl($fileId));
			$attach->setDeepLinkUrl($this->fileService->getDeepLinkUrl($fileId));

			$rAttachts[] = $attach;
		}
		$note->setAttachts($rAttachts);

		$permissions = $isOwner
			? NoteShare::PERMISSIONS_ALL
			: $this->shareService->getPermissions($userId, $note, $shares);
		$note->setPermissions($permissions);
		$note->setIsOwner($isOwner);

		$owner = $this->userManager->get($note->getUserId());
		$note->setOwnerDisplayName(is_null($owner) ? $note->getUserId() : $owner->getDisplayName());

		// Who the note is shared with is only shown to somebody who could do
		// something about it. A recipient with no say has no business knowing
		// the rest of the list.
		$canSeeShares = $isOwner || ($permissions & NoteShare::PERMISSION_SHARE) !== 0;
		$note->setSharedWith($canSeeShares ? array_values($shares) : []);

		// Leaving needs a share made with this user personally; a group share
		// is not theirs to drop, and archiving is what they have instead.
		$note->setCanLeave(!$isOwner && count(array_filter($shares, function (NoteShare $share) use ($userId) {
			return !$share->isGroupShare() && $share->getShareWith() === $userId;
		})) > 0);

		$note->setSharedBy($isOwner ? null : [
			'uid' => $note->getUserId(),
			'displayName' => is_null($owner) ? $note->getUserId() : $owner->getDisplayName(),
		]);

		return $note;
	}

}
