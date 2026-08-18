<?php declare(strict_types=1);
/*
 * @copyright 2026 Matias De lellis <mati86dl@gmail.com>
 *
 * @author 2026 Matias De lellis <mati86dl@gmail.com>
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

use OCA\QuickNotes\AppInfo\Application;
use OCA\QuickNotes\Db\AttachMapper;
use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Db\NoteMapper;
use OCA\QuickNotes\Db\NoteShare;
use OCA\QuickNotes\Db\NoteShareMapper;
use OCA\QuickNotes\Db\NoteStateMapper;
use OCA\QuickNotes\Db\NoteTagMapper;
use OCA\QuickNotes\Exception\ForbiddenException;
use OCA\QuickNotes\Notification\Notifier;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\IShare;

use Psr\Log\LoggerInterface;

/**
 * Everything about who else can see a note, and what they can do with it.
 *
 * This is the only place that answers "may this user do this to this note".
 * `NoteService` asks it and acts on the answer; nothing else is allowed to
 * decide for itself, because until 0.9.1 the answer was implicit — every query
 * filtered by owner, and a share was a read-only curiosity that could not
 * write anything anyway.
 *
 * The model is the server's own: a share points at a user or at a group
 * (`NoteShare::TYPE_*`, the values of `IShare::TYPE_*`) and carries a bitmask
 * of `NoteShare::PERMISSION_*`, the values of `OCP\Constants`. The effective
 * permissions of a user on a note are the union of every share that reaches
 * them, directly or through a group; the owner always has all of them.
 *
 * What a share can never grant, no matter the permissions: the colour, the
 * attachments, archiving, trashing and destroying stay with the owner. Those
 * either belong to the owner's files (attachments) or decide whether the note
 * exists at all. What *is* personal — the pin, the tags and the reminder — is
 * per user by construction and needs no permission from anybody.
 */
class ShareService {

	/** @var NoteShareMapper */
	private $noteShareMapper;

	/** @var NoteMapper */
	private $noteMapper;

	/** @var NoteStateMapper */
	private $noteStateMapper;

	/** @var NoteTagMapper */
	private $noteTagMapper;

	/** @var AttachMapper */
	private $attachMapper;

	/** @var IUserManager */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IConfig */
	private $config;

	/** @var ISearch */
	private $collaboratorSearch;

	/** @var INotificationManager */
	private $notificationManager;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * Group memberships resolved during this request, keyed by user id. A
	 * listing of notes asks for the same user over and over.
	 *
	 * @var array<string, string[]>
	 */
	private $groupIdsCache = [];

	public function __construct(NoteShareMapper      $noteShareMapper,
	                            NoteMapper           $noteMapper,
	                            NoteStateMapper      $noteStateMapper,
	                            NoteTagMapper        $noteTagMapper,
	                            AttachMapper         $attachMapper,
	                            IUserManager         $userManager,
	                            IGroupManager        $groupManager,
	                            IConfig              $config,
	                            ISearch              $collaboratorSearch,
	                            INotificationManager $notificationManager,
	                            ITimeFactory         $timeFactory,
	                            LoggerInterface      $logger)
	{
		$this->noteShareMapper     = $noteShareMapper;
		$this->noteMapper          = $noteMapper;
		$this->noteStateMapper     = $noteStateMapper;
		$this->noteTagMapper       = $noteTagMapper;
		$this->attachMapper        = $attachMapper;
		$this->userManager         = $userManager;
		$this->groupManager        = $groupManager;
		$this->config              = $config;
		$this->collaboratorSearch  = $collaboratorSearch;
		$this->notificationManager = $notificationManager;
		$this->timeFactory         = $timeFactory;
		$this->logger              = $logger;
	}

	/**
	 * What this user may do with this note.
	 *
	 * @return int bitmask of NoteShare::PERMISSION_*, 0 when they may not even
	 *             see it
	 */
	public function getPermissions(string $userId, Note $note, ?array $shares = null): int {
		if ($note->getUserId() === $userId) {
			return NoteShare::PERMISSIONS_ALL;
		}

		// Listing notes already fetched every share of every note in one
		// query; asking again per note would be one round trip each.
		if (is_null($shares)) {
			$applicable = $this->getSharesFor($userId, (int)$note->getId());
		} else {
			$groupIds = $this->getGroupIds($userId);
			$applicable = array_filter($shares, function (NoteShare $share) use ($userId, $groupIds) {
				return $this->appliesTo($share, $userId, $groupIds);
			});
		}

		$permissions = 0;
		foreach ($applicable as $share) {
			$permissions |= $share->getPermissions();
		}

		return $permissions & NoteShare::PERMISSIONS_ALL;
	}

	/**
	 * Whether this user can see this note at all. The question an attachment
	 * asks about the person who attached it, and the note asks about whoever
	 * is looking.
	 */
	public function canSee(string $userId, Note $note, ?array $shares = null): bool {
		return $this->getPermissions($userId, $note, $shares) !== 0;
	}

	/**
	 * Whether a share is one that reaches this user.
	 *
	 * @param string[] $groupIds
	 */
	private function appliesTo(NoteShare $share, string $userId, array $groupIds): bool {
		if ($share->isGroupShare()) {
			return in_array($share->getShareWith(), $groupIds, true);
		}
		return $share->getShareWith() === $userId;
	}

	/**
	 * The shares that reach a user on a note: the one made with them, plus the
	 * ones made with their groups.
	 *
	 * @return NoteShare[]
	 */
	public function getSharesFor(string $userId, int $noteId): array {
		return $this->noteShareMapper->findByNoteAndRecipient(
			$noteId, $userId, $this->getGroupIds($userId)
		);
	}

	/**
	 * Ids of every note somebody else shared with this user.
	 *
	 * @return int[]
	 */
	public function getSharedNoteIds(string $userId): array {
		$ids = [];
		foreach ($this->noteShareMapper->findByRecipient($userId, $this->getGroupIds($userId)) as $share) {
			$ids[$share->getNoteId()] = true;
		}
		return array_keys($ids);
	}

	/**
	 * The shares of a note, with the display names filled in.
	 *
	 * @return NoteShare[]
	 */
	public function getSharesForNote(int $noteId): array {
		return array_map([$this, 'fillDisplayNames'], $this->noteShareMapper->findByNoteId($noteId));
	}

	/**
	 * The same, for several notes at once and in a single query.
	 *
	 * @param int[] $noteIds
	 * @return array<int, NoteShare[]>
	 */
	public function getSharesForNotes(array $noteIds): array {
		$shares = $this->noteShareMapper->findByNoteIds($noteIds);
		foreach ($shares as $noteId => $noteShares) {
			$shares[$noteId] = array_map([$this, 'fillDisplayNames'], $noteShares);
		}
		return $shares;
	}

	/**
	 * Share a note with a user or a group.
	 *
	 * @param string $actorId who is sharing
	 * @param Note $note the note being shared
	 * @param int $shareType NoteShare::TYPE_USER or NoteShare::TYPE_GROUP
	 * @param string $shareWith uid or gid, depending on the type
	 * @param int|null $permissions bitmask, read only when not given
	 *
	 * @throws ForbiddenException if the actor may not share this note, or may
	 *         not hand out these permissions
	 * @throws \InvalidArgumentException if the recipient makes no sense
	 */
	public function create(string $actorId,
	                       Note   $note,
	                       int    $shareType,
	                       string $shareWith,
	                       ?int   $permissions = null): NoteShare
	{
		$permissions = $permissions ?? NoteShare::PERMISSIONS_DEFAULT;

		$actorPermissions = $this->getPermissions($actorId, $note);
		if (($actorPermissions & NoteShare::PERMISSION_SHARE) === 0) {
			throw new ForbiddenException('You are not allowed to share this note');
		}
		$this->assertPermissionsWithin($permissions, $actorPermissions);

		$shareWith = trim($shareWith);
		if ($shareWith === '') {
			throw new \InvalidArgumentException('No recipient given');
		}

		switch ($shareType) {
			case NoteShare::TYPE_USER:
				$this->assertUserShareable($actorId, $note, $shareWith);
				break;
			case NoteShare::TYPE_GROUP:
				$this->assertGroupShareable($shareWith);
				break;
			default:
				throw new \InvalidArgumentException('Unsupported share type');
		}

		if ($this->noteShareMapper->existsByNoteAndTarget((int)$note->getId(), $shareType, $shareWith)) {
			throw new \InvalidArgumentException('The note is already shared with this recipient');
		}

		$share = new NoteShare();
		$share->setNoteId((int)$note->getId());
		$share->setShareType($shareType);
		$share->setShareWith($shareWith);
		$share->setPermissions($permissions);
		$share->setUidOwner($note->getUserId());
		$share->setUidInitiator($actorId);
		$share->setCreatedAt($this->timeFactory->getTime());

		$share = $this->noteShareMapper->insert($share);

		$this->notifyShared($share, $note);

		return $this->fillDisplayNames($share);
	}

	/**
	 * Change what a share grants.
	 *
	 * @throws ForbiddenException
	 * @throws DoesNotExistException
	 */
	public function updatePermissions(string $actorId, int $shareId, int $permissions): NoteShare {
		$share = $this->noteShareMapper->find($shareId);
		$note = $this->noteMapper->findShared($share->getNoteId());

		$this->assertMayManage($actorId, $note, $share);
		$this->assertPermissionsWithin($permissions, $this->getPermissions($actorId, $note));

		$share->setPermissions($permissions);
		$share = $this->noteShareMapper->update($share);

		return $this->fillDisplayNames($share);
	}

	/**
	 * Take a share back.
	 *
	 * @throws ForbiddenException
	 * @throws DoesNotExistException
	 */
	public function delete(string $actorId, int $shareId): NoteShare {
		$share = $this->noteShareMapper->find($shareId);
		$note = $this->noteMapper->findShared($share->getNoteId());

		$this->assertMayManage($actorId, $note, $share);

		$this->noteShareMapper->delete($share);
		$this->forgetRecipientState($share);
		$this->dismissNotification($share);
		$this->pruneOrphanReshares($note);

		return $share;
	}

	/**
	 * A user walking away from a note somebody shared with them.
	 *
	 * Only a share made with them personally can be left: leaving a group
	 * share would take the note from the whole group, which is not theirs to
	 * decide. For those the note simply stays until the owner unshares it.
	 *
	 * @return bool false when there was no such share to begin with
	 */
	public function leave(string $actorId, int $noteId): bool {
		try {
			$share = $this->noteShareMapper->findByNoteAndTarget($noteId, NoteShare::TYPE_USER, $actorId);
		} catch (DoesNotExistException $e) {
			return false;
		}

		$this->noteShareMapper->delete($share);
		$this->forgetRecipientState($share);
		$this->dismissNotification($share);

		try {
			$this->pruneOrphanReshares($this->noteMapper->findShared($noteId));
		} catch (DoesNotExistException $e) {
			// The note went in the meantime; there is nothing left to prune.
		}

		return true;
	}

	/**
	 * Drop the shares whose author can no longer see the note.
	 *
	 * A reshare hangs off the access of whoever made it, so taking that access
	 * away has to take the reshare with it: unsharing a note from somebody who
	 * had passed it on used to leave the third person holding a share that
	 * nobody was entitled to give them any more. The server cascades its own
	 * reshares for exactly this reason.
	 *
	 * Written as a sweep rather than as a walk down the tree because that is
	 * what makes it right for group shares too — the "recipient" of one is a
	 * set of people, any of whom may still reach the note by another share —
	 * and it holds the invariant directly: no share outlives its author's
	 * access. It repeats until a pass changes nothing, so a chain (a reshare of
	 * a reshare) unwinds in full.
	 *
	 * The owner's own shares are never touched: their access is the note.
	 */
	private function pruneOrphanReshares(Note $note): void {
		$noteId = (int)$note->getId();

		do {
			$removed = false;

			foreach ($this->noteShareMapper->findByNoteId($noteId) as $share) {
				$initiator = $share->getUidInitiator();

				if ($initiator === '' || is_null($initiator) || $initiator === $note->getUserId()) {
					continue;
				}
				if ($this->canSee($initiator, $note)) {
					continue;
				}

				$this->logger->info('quicknotes: dropping the share of note ' . $noteId
					. ' with ' . $share->getShareWith() . ', made by ' . $initiator
					. ' who can no longer see it');

				$this->noteShareMapper->delete($share);
				$this->forgetRecipientState($share);
				$this->dismissNotification($share);
				$removed = true;
			}
		} while ($removed);
	}

	/**
	 * Drop every share of a note. Called when the note itself is destroyed.
	 */
	public function deleteForNote(int $noteId): void {
		foreach ($this->noteShareMapper->findByNoteId($noteId) as $share) {
			$this->dismissNotification($share);
		}
		$this->noteShareMapper->deleteByNoteId($noteId);
	}

	/**
	 * Bring the user shares of a note in line with a list of user ids.
	 *
	 * This is the shape the v1 API has always spoken — the whole note, shares
	 * included, in a single PUT — and it is kept for the clients that speak
	 * it. The dialog of the app does not: it creates and removes shares one at
	 * a time through their own endpoints, so that sharing a note no longer
	 * depends on remembering to save it afterwards, and so that a stale list
	 * in a browser tab cannot silently revoke a share made somewhere else.
	 *
	 * Existing shares keep their permissions; new ones are read only.
	 *
	 * @param array $sharedWith list of ['id' => uid] or NoteShare-ish arrays
	 */
	public function syncUserShares(string $ownerId, Note $note, array $sharedWith): void {
		if ($note->getUserId() !== $ownerId) {
			throw new ForbiddenException('Only the owner can replace the shares of a note');
		}

		$wanted = [];
		foreach ($sharedWith as $entry) {
			$uid = null;
			if (is_string($entry)) {
				$uid = $entry;
			} elseif (is_array($entry)) {
				$uid = $entry['shareWith'] ?? $entry['id'] ?? $entry['shared_user'] ?? null;
			}
			if (is_string($uid) && $uid !== '') {
				$wanted[$uid] = true;
			}
		}

		foreach ($this->noteShareMapper->findByNoteId((int)$note->getId()) as $share) {
			if ($share->isGroupShare()) {
				// Group shares have no representation in the old payload, so
				// its absence says nothing about them.
				continue;
			}
			if (isset($wanted[$share->getShareWith()])) {
				unset($wanted[$share->getShareWith()]);
				continue;
			}
			$this->noteShareMapper->delete($share);
			$this->forgetRecipientState($share);
			$this->dismissNotification($share);
		}

		foreach (array_keys($wanted) as $uid) {
			try {
				$this->create($ownerId, $note, NoteShare::TYPE_USER, $uid, NoteShare::PERMISSIONS_DEFAULT);
			} catch (\InvalidArgumentException $e) {
				// A user that no longer exists, or the owner themselves. The
				// rest of the list is still worth applying.
				$this->logger->debug('Skipped share with ' . $uid . ': ' . $e->getMessage());
			}
		}
	}

	/**
	 * Users and groups this note could still be shared with.
	 *
	 * Goes through the collaborator search of the server, which is what the
	 * files sharing dialog uses, so the admin settings about who may see whom
	 * (`shareapi_allow_share_dialog_user_enumeration` and friends) are honoured
	 * without this app having to know they exist.
	 *
	 * @return array<int, array{shareType: int, shareWith: string, label: string, subline: string}>
	 */
	public function searchSharees(string $actorId, Note $note, string $search, int $limit = 25): array {
		$shareTypes = [IShare::TYPE_USER];
		if ($this->isGroupSharingAllowed()) {
			$shareTypes[] = IShare::TYPE_GROUP;
		}

		[$result] = $this->collaboratorSearch->search($search, $shareTypes, false, $limit, 0);

		// Whoever is already on the note, plus the owner and the person
		// searching: offering them again could only end in an error.
		$taken = [
			NoteShare::TYPE_USER => [$actorId => true, $note->getUserId() => true],
			NoteShare::TYPE_GROUP => [],
		];
		foreach ($this->noteShareMapper->findByNoteId((int)$note->getId()) as $share) {
			$taken[$share->getShareType()][$share->getShareWith()] = true;
		}

		$sharees = [];
		$buckets = [
			NoteShare::TYPE_USER => ['users', 'exact.users'],
			NoteShare::TYPE_GROUP => ['groups', 'exact.groups'],
		];

		foreach ($buckets as $shareType => $keys) {
			foreach ($keys as $key) {
				$entries = (strpos($key, 'exact.') === 0)
					? ($result['exact'][substr($key, 6)] ?? [])
					: ($result[$key] ?? []);

				foreach ($entries as $entry) {
					$shareWith = $entry['value']['shareWith'] ?? null;
					if (!is_string($shareWith) || isset($taken[$shareType][$shareWith])) {
						continue;
					}
					$taken[$shareType][$shareWith] = true;
					$sharees[] = [
						'shareType' => $shareType,
						'shareWith' => $shareWith,
						'label' => (string)($entry['label'] ?? $shareWith),
						'subline' => (string)($entry['subline'] ?? ''),
					];
				}
			}
		}

		return $sharees;
	}

	/**
	 * The groups a user belongs to, remembered for the rest of the request.
	 *
	 * @return string[]
	 */
	private function getGroupIds(string $userId): array {
		if (isset($this->groupIdsCache[$userId])) {
			return $this->groupIdsCache[$userId];
		}

		$user = $this->userManager->get($userId);
		$groupIds = is_null($user) ? [] : $this->groupManager->getUserGroupIds($user);

		$this->groupIdsCache[$userId] = $groupIds;
		return $groupIds;
	}

	private function isGroupSharingAllowed(): bool {
		return $this->config->getAppValue('core', 'shareapi_allow_group_sharing', 'yes') === 'yes';
	}

	/**
	 * @throws ForbiddenException
	 */
	private function assertPermissionsWithin(int $permissions, int $available): void {
		if (!NoteShare::arePermissionsValid($permissions)) {
			throw new \InvalidArgumentException('Invalid permissions');
		}
		if (($permissions & ~$available) !== 0) {
			throw new ForbiddenException('You cannot grant more than you have');
		}
	}

	/**
	 * Who may change or revoke a share: the owner of the note, always, and
	 * whoever created the share, so a reshare stays in the hands of the person
	 * who made it.
	 *
	 * @throws ForbiddenException
	 */
	private function assertMayManage(string $actorId, Note $note, NoteShare $share): void {
		if ($note->getUserId() === $actorId) {
			return;
		}
		if ($share->getUidInitiator() === $actorId
		    && ($this->getPermissions($actorId, $note) & NoteShare::PERMISSION_SHARE) !== 0) {
			return;
		}
		throw new ForbiddenException('You are not allowed to manage this share');
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private function assertUserShareable(string $actorId, Note $note, string $shareWith): void {
		if ($shareWith === $note->getUserId()) {
			throw new \InvalidArgumentException('The note already belongs to this user');
		}
		if (!$this->userManager->userExists($shareWith)) {
			throw new \InvalidArgumentException('No such user');
		}

		// The admin can confine sharing to people one shares a group with. The
		// collaborator search already hides everybody else, but a request can
		// be made without asking it first.
		$onlyGroupMembers = $this->config->getAppValue('core', 'shareapi_only_share_with_group_members', 'no') === 'yes';
		if ($onlyGroupMembers) {
			$common = array_intersect($this->getGroupIds($actorId), $this->getGroupIds($shareWith));
			if (count($common) === 0) {
				throw new \InvalidArgumentException('You may only share with users of your own groups');
			}
		}
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private function assertGroupShareable(string $shareWith): void {
		if (!$this->isGroupSharingAllowed()) {
			throw new \InvalidArgumentException('Group sharing is disabled on this server');
		}
		if (!$this->groupManager->groupExists($shareWith)) {
			throw new \InvalidArgumentException('No such group');
		}
	}

	/**
	 * A note that is no longer shared with somebody should not keep their pin,
	 * their tags, their reminder and their attachments around either. The
	 * first three are of no use to anyone and would come back if the note were
	 * shared again; the attachments are the sharper case, since a file of
	 * theirs would otherwise go on being served to the note's audience after
	 * they are out of it. The files themselves are untouched.
	 */
	private function forgetRecipientState(NoteShare $share): void {
		if ($share->isGroupShare()) {
			// The members of the group are not enumerated here on purpose: a
			// group can be large, and the rows are harmless — a reminder left
			// behind on a note the user can no longer see is dropped by
			// `ReminderService::notifyDue()` when it comes due, and an
			// attachment of theirs stops being listed and served because
			// `NoteService::hydrate()` asks whether the person who attached it
			// can still see the note. Both check access anyway, for the user
			// who quietly left the group rather than being unshared.
			return;
		}
		$this->noteStateMapper->deleteForUser($share->getShareWith(), $share->getNoteId());
		$this->noteTagMapper->deleteForUser($share->getShareWith(), $share->getNoteId());
		$this->attachMapper->deleteForUser($share->getShareWith(), $share->getNoteId());
	}

	/**
	 * Tell the recipient of a new share about it.
	 *
	 * Only for user shares: a group share would mean one notification per
	 * member, which for a large group is a lot of noise for something the
	 * recipient will see in the app anyway.
	 */
	private function notifyShared(NoteShare $share, Note $note): void {
		if ($share->isGroupShare()) {
			return;
		}

		$sharer = $this->userManager->get($share->getUidInitiator());
		if (is_null($sharer)) {
			return;
		}

		try {
			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($share->getShareWith())
				->setDateTime($this->timeFactory->getDateTime())
				->setObject(Notifier::OBJECT_NOTE, (string)$share->getNoteId())
				->setSubject(Notifier::SUBJECT_SHARE, [
					'title' => strip_tags($note->getTitle()),
					'sharedBy' => $sharer->getUID(),
					'sharedByDisplayName' => $sharer->getDisplayName(),
				]);
			$this->notificationManager->notify($notification);
		} catch (\Throwable $e) {
			// A share that happened is worth more than the notification about
			// it, so this never takes the request down with it.
			$this->logger->warning('Could not notify about a shared note', ['exception' => $e]);
		}
	}

	/**
	 * Withdraw the notifications of a recipient about a note that is no longer
	 * theirs to see.
	 *
	 * Deliberately *not* scoped to the share subject, unlike
	 * `ReminderService::dismiss()`: somebody who just lost access should not
	 * be left holding a reminder about the note either, and their reminder row
	 * went with `forgetRecipientState()` right before this.
	 */
	private function dismissNotification(NoteShare $share): void {
		if ($share->isGroupShare()) {
			return;
		}

		try {
			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($share->getShareWith())
				->setObject(Notifier::OBJECT_NOTE, (string)$share->getNoteId());
			$this->notificationManager->markProcessed($notification);
		} catch (\Throwable $e) {
			$this->logger->warning('Could not withdraw the notification of a share', ['exception' => $e]);
		}
	}

	/**
	 * Resolve the uid or gid of a share into something worth showing.
	 */
	private function fillDisplayNames(NoteShare $share): NoteShare {
		if ($share->isGroupShare()) {
			$group = $this->groupManager->get($share->getShareWith());
			$share->setDisplayName(is_null($group) ? $share->getShareWith() : $group->getDisplayName());
		} else {
			$user = $this->userManager->get($share->getShareWith());
			$share->setDisplayName(is_null($user) ? $share->getShareWith() : $user->getDisplayName());
		}

		$owner = $this->userManager->get($share->getUidOwner());
		$share->setOwnerDisplayName(is_null($owner) ? $share->getUidOwner() : $owner->getDisplayName());

		return $share;
	}

}
