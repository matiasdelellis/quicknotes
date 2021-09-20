<?php
namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;
use OCP\AppFramework\Db\DoesNotExistException;

use OCA\QuickNotes\Db\NoteShare;

class NoteShareMapper extends Mapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_shares', '\OCA\QuickNotes\Db\NoteShare');
	}

	/*public function find($id, $userId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_shares WHERE id = ? AND user_id = ?';
		return $this->findEntity($sql, [$id, $userId]);
	}*/

	public function findForUser(string $userId): array {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_shares WHERE shared_user = ?';
		return $this->findEntities($sql, [$userId]);
	}

	public function findForGroup($groupId): array {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_shares WHERE shared_group = ?';
		return $this->findEntities($sql, [$groupId]);
	}

	public function findByNoteAndUser($noteId, $userId): NoteShare {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_shares WHERE shared_user = ? AND note_id = ?';
		return $this->findEntity($sql, [$userId, $noteId]);
	}

	public function findByNoteAndGroup($noteId, $groupId): NoteShare {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_shares WHERE shared_group = ? AND note_id = ?';
		return $this->findEntity($sql, [$groupId, $noteId]);
	}

	public function getSharesForNote(int $noteId): array {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_shares WHERE note_id = ?';
		return $this->findEntities($sql, [$noteId]);
	}

	public function deleteByNoteId(int $noteId): void {
		$sql = 'DELETE FROM *PREFIX*quicknotes_shares WHERE note_id = ?';
		$this->execute($sql, [$noteId]);
	}

	/**
	 * @return bool
	 */
	public function existsByNoteAndUser(int $noteId, $userId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_shares WHERE shared_user = ? AND note_id = ?';
		try {
			$this->findEntities($sql, [$userId, $noteId]);
		} catch (DoesNotExistException $e) {
			return false;
		}
		return true;
	}
}
