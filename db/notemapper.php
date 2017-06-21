<?php
namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;
use OCP\AppFramework\Db\DoesNotExistException;

class NoteMapper extends Mapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_notes', '\OCA\QuickNotes\Db\Note');
	}

	/**
	 * @param int $id
	 * @param string $userId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @return Note
	 */
	public function find($id, $userId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_notes WHERE id = ? AND user_id = ?';
		return $this->findEntity($sql, [$id, $userId]);
	}

	public function findById($id) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_notes WHERE id = ?';
		return $this->findEntity($sql, [$id]);
	}

	public function findAll($userId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_notes WHERE user_id = ?';
		return $this->findEntities($sql, [$userId]);
	}

	public function colorIdCount($colorid) {
		$sql = 'SELECT COUNT(*) as `count` FROM *PREFIX*quicknotes_notes WHERE color_id = ?';
		$result = $this->execute($sql, [$colorid]);
		$row = $result->fetch();
		$result->closeCursor();
		return $row['count'];
	}

}
