<?php
namespace OCA\QuickNotes\Db;

use OCP\IDb;
use OCP\AppFramework\Db\Mapper;

class NoteMapper extends Mapper {

	public function __construct(IDb $db) {
		parent::__construct($db, 'quicknotes_notes', '\OCA\QuickNotes\Db\Note');
	}

	public function find($id, $userId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_notes WHERE id = ? AND user_id = ?';
		return $this->findEntity($sql, [$id, $userId]);
	}

	public function findAll($userId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_notes WHERE user_id = ?';
		return $this->findEntities($sql, [$userId]);
	}

}