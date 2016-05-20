<?php
namespace OCA\QuickNotes\Db;

use OCP\IDb;
use OCP\AppFramework\Db\Mapper;

class TaskMapper extends Mapper {

	public function __construct(IDb $db) {
		parent::__construct($db, 'quicknotes_tasks', '\OCA\QuickNotes\Db\Tasks');
	}

	public function find($id, $noteId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_tasks WHERE id = ? AND note_id = ?';
		return $this->findEntity($sql, [$id, $noteId]);
	}

	public function findAll($noteId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_tasks WHERE note_id = ?';
		return $this->findEntities($sql, [$noteId]);
	}

}