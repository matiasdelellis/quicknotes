<?php
/**
 * ownCloud - quicknotes
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matias De lellis <mati86dl@gmail.com>
 * @copyright Matias De lellis 2016
 */

namespace OCA\QuickNotes\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Controller;

use OCA\QuickNotes\Db\Color;
use OCA\QuickNotes\Db\ColorMapper;
use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Db\NoteMapper;
use OCA\QuickNotes\Db\NoteTag;
use OCA\QuickNotes\Db\NoteTagMapper;
use OCA\QuickNotes\Db\NoteShare;
use OCA\QuickNotes\Db\NoteShareMapper;
use OCA\QuickNotes\Db\Tag;
use OCA\QuickNotes\Db\TagMapper;

class NoteController extends Controller {

	private $notemapper;
	private $notetagmapper;
	private $colormapper;
	private $notesharemapper;
	private $tagmapper;
	private $userId;

	public function __construct($AppName,
	                            IRequest        $request,
	                            NoteMapper      $notemapper,
	                            NoteTagMapper   $notetagmapper,
	                            NoteShareMapper $notesharemapper,
	                            ColorMapper     $colormapper,
	                            TagMapper       $tagmapper,
	                            $UserId)
	{
		parent::__construct($AppName, $request);
		$this->notemapper = $notemapper;
		$this->notetagmapper = $notetagmapper;
		$this->colormapper = $colormapper;
		$this->notesharemapper = $notesharemapper;
		$this->tagmapper = $tagmapper;
		$this->userId = $UserId;
	}

	/**
	 * @NoAdminRequired
	 */
	 public function index() {
		$notes = $this->notemapper->findAll($this->userId);
		foreach($notes as $note) {
			$note->setIsShared(false);
			$sharedWith = $this->notesharemapper->getSharesForNote($note->getId());
			if(count($sharedWith) > 0) {
				$shareList = array();
				foreach($sharedWith as $share) {
					$shareList[] = $share->getSharedUser();
				}
				$note->setSharedWith(implode(", ", $shareList));
			} else {
				$note->setSharedWith(null);
			}
			$note->setTags($this->tagmapper->getTagsForNote($this->userId, $note->getId()));
		}
		$shareEntries = $this->notesharemapper->findForUser($this->userId);
		$shares = array();
		foreach($shareEntries as $entry) {
			try {
				//find is only to check if current user is owner
				$this->notemapper->find($entry->getNoteId(), $this->userId);
				//user is owner, nothing to do
			} catch(\OCP\AppFramework\Db\DoesNotExistException $e) {
				$share = $this->notemapper->findById($entry->getNoteId());
				$share->setIsShared(true);
				$shares[] = $share;
			}
		}
		$notes = array_merge($notes, $shares);
		// Insert true color to response
		foreach ($notes as $note) {
			$note->setColor($this->colormapper->find($note->getColorId())->getColor());
		}
		return new DataResponse($notes);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 */
	public function show($id) {
		// TODO: Implement.
		try {
			return new DataResponse($this->notemapper->find($id, $this->userId));
		} catch(Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $title
	 * @param string $content
	 */
	public function create($title, $content, $color = "#F7EB96") {
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
		$note->setUserId($this->userId);

		// Insert true color to response
		$note->setColor($hcolor->getColor());

		return new DataResponse($this->notemapper->insert($note));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @param string $title
	 * @param string $content
	 * @param array $tags
	 * @param string $color
	 */
	public function update($id, $title, $content, $tags, $color = "#F7EB96") {
		// Get current Note and Color.
		try {
			$note = $this->notemapper->find($id, $this->userId);
		} catch(Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$oldcolorid = $note->getColorId();

		// Get new Color or append it.
		if ($this->colormapper->colorExists($color)) {
			$hcolor = $this->colormapper->findByColor($color);
		} else {
			$hcolor = new Color();
			$hcolor->setColor($color);
			$hcolor = $this->colormapper->insert($hcolor);
		}

		// Delete old tag relations
		$dbTags = $this->tagmapper->getTagsForNote($this->userId, $id);
		foreach ($dbTags as $dbTag) {
			$delete = true;
			foreach ($tags as $tag) {
				if ($dbTag->getId() == $tag['id']) {
					$delete = false;
					break;
				}
			}
			if ($delete) {
				$hnotetag = $this->notetagmapper->findNoteTag($this->userId, $id, $dbTag->getId());
				$this->notetagmapper->delete($hnotetag);
			}
		}

		// Add new tags and update relations.
		foreach ($tags as $tag) {
			if (!$this->tagmapper->tagExists($this->userId, $tag['name'])) {
				$htag = new Tag();
				$htag->setName($tag['name']);
				$htag->setUserId($this->userId);
				$htag = $this->tagmapper->insert($htag);
			}
			else {
				$htag = $this->tagmapper->getTag($this->userId, $tag['name']);
			}

			if (!$this->notetagmapper->noteTagExists($this->userId, $id, $htag->getId())) {
				$noteTag = new NoteTag();
				$noteTag->setNoteId($id);
				$noteTag->setTagId($htag->getId());
				$noteTag->setUserId($this->userId);
				$this->notetagmapper->insert($noteTag);
			}
		}

		// Purge orphan tags.
		$this->tagmapper->dropOld();

		// Set new info on Note
		$note->setTitle($title);
		$note->setContent($content);
		$note->setTimestamp(time());
		$note->setColorId($hcolor->id);
		// Insert true color to response
		$note->setColor($hcolor->getColor());

		// Update note.
		$newnote = $this->notemapper->update($note);

		//  Remove old color if necessary
		if (($oldcolorid !== $hcolor->getId()) &&
		    (!$this->notemapper->colorIdCount($oldcolorid))) {
			$oldcolor = $this->colormapper->find($oldcolorid);
			$this->colormapper->delete($oldcolor);
		}

		return new DataResponse($newnote);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 */
	public function destroy($id) {
		// Get Note and Color
		try {
			$note = $this->notemapper->find($id, $this->userId);
		} catch(Exception $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		$oldcolorid = $note->getColorId();

		$this->notesharemapper->deleteByNoteId($note->getId());

		// Delete note.
		$this->notemapper->delete($note);

		// Delete Color if necessary
		if (!$this->notemapper->colorIdCount($oldcolorid)) {
			$oldcolor = $this->colormapper->find($oldcolorid);
			$this->colormapper->delete($oldcolor);
		}

		return new DataResponse($note);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getUserGroupsAndUsersWithShare($noteId) {
		$userMgr = \OC::$server->getUserManager();
		$grpMgr = \OC::$server->getGroupManager();
		$users = array();
		$groups = array();
		if($grpMgr->isAdmin($this->userId)) {
			$igroups = $grpMgr->search("");
			$iusers = $userMgr->search("");
			foreach($igroups as $g) {
				$groups[] = $g->getGID();
			}
			foreach($iusers as $u) {
				$users[] = $u->getUID();
			}
		} else {
			$igroups = $grpMgr->getUserGroups($userMgr->get($this->userId));
			foreach($igroups as $g) {
				$iusers = $g->getUsers();
				foreach($iusers as $u) {
					$users[] = $u->getUID();
				}
				$groups[] = $g->getGID();
			}
		}

		$users = array_unique($users);
		if(($i = array_search($this->userId, $users)) !== false) {
			unset($users[$i]);
		}
		$pos_users = array();
		$pos_groups = array();
		$shares = $this->notesharemapper->getSharesForNote($noteId);
		foreach($shares as $s) {
			$shareType = $s->getSharedUser();
			if(strlen($shareType) !== 0) {
				if(($i = array_search($shareType, $users)) !== false) {
					unset($users[$i]);
					$pos_users[] = $shareType;
				}
			} else {
				$shareType = $s->getSharedGroup();
				if(($i = array_search($shareType, $groups)) !== false) {
					unset($groups[$i]);
					$pos_groups[] = $shareType;
				}
			}
		}
		$params = array('groups' => $groups, 'users' => $users, 'posGroups' => $pos_groups, 'posUsers' => $pos_users);
		return new JSONResponse($params);
	}

	/**
	 * @NoAdminRequired
	 */
	public function addGroupShare($groupId, $noteId) {
	    $share = new NoteShare();
	    $share->setSharedGroup($groupId);
	    $share->setNoteId($noteId);
	    $this->notesharemapper->insert($share);
	}

	/**
	 * @NoAdminRequired
	 */
	public function removeGroupShare($groupId, $noteId) {
		$share = $this->notesharemapper->findByNoteAndGroup($noteId, $groupId);
		$this->notesharemapper->delete($share);
	}

	/**
	 * @NoAdminRequired
	 */
	public function addUserShare($userId, $noteId) {
		$share = new NoteShare();
		$share->setSharedUser($userId);
		$share->setNoteId($noteId);
		$this->notesharemapper->insert($share);
	}

	/**
	 * @NoAdminRequired
	 */
	public function removeUserShare($userId, $noteId) {
		$share = $this->notesharemapper->findByNoteAndUser($noteId, $userId);
		$this->notesharemapper->delete($share);
	}
}
