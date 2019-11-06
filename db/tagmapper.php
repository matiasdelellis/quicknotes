<?php
namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;

class TagMapper extends Mapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_tags', '\OCA\QuickNotes\Db\Tag');
	}

	public function find($id, $userId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_tags WHERE id = ? AND user_id = ?';
		return $this->findEntity($sql, [$id, $userId]);
	}

	public function findAll($userId) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_tags WHERE user_id = ?';
		return $this->findEntities($sql, [$userId]);
	}

	public function getTagsForNote ($noteId) {
		$sql = 'SELECT T.id, T.name FROM *PREFIX*quicknotes_tags T ';
		$sql.= 'INNER JOIN *PREFIX*quicknotes_note_tags NT ';
		$sql.= 'ON T.id = NT.tag_id ';
		$sql.= 'WHERE NT.note_id = ?';
		return $this->findEntities($sql, [$noteId]);
	}

}