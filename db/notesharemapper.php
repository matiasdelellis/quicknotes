<?php
namespace OCA\QuickNotes\Db;

use OCP\IDb;
use OCP\AppFramework\Db\Mapper;
use OCP\AppFramework\Db\DoesNotExistException;

class NoteShareMapper extends Mapper {

	public function __construct(IDb $db) {
		parent::__construct($db, 'quicknotes_shares', '\OCA\QuickNotes\Db\NoteShare');
	}

	/*public function find($id, $userId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_shares WHERE id = ? AND user_id = ?';
		return $this->findEntity($sql, [$id, $userId]);
	}*/

	public function findForUser($userId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_shares WHERE shared_user = ?';
		return $this->findEntities($sql, [$userId]);
	}

	public function findForGroup($groupId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_shares WHERE shared_group = ?';
		return $this->findEntities($sql, [$groupId]);
	}

	public function findByNoteAndUser($noteId, $userId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_shares WHERE shared_user = ? AND note_id = ?';
		return $this->findEntity($sql, [$userId, $noteId]);
	}

	public function findByNoteAndGroup($noteId, $groupId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_shares WHERE shared_group = ? AND note_id = ?';
		return $this->findEntity($sql, [$groupId, $noteId]);
	}

	public function getSharesForNote($noteId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_shares WHERE note_id = ?';
		return $this->findEntities($sql, [$noteId]);
	}

	public function deleteByNoteId($noteId) {
		$sql = 'DELETE FROM *PREFIX*quicknotes_shares WHERE note_id = ?';
		$this->execute($sql, [$noteId]);
	}
}
