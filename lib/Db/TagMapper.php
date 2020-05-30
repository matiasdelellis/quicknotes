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

	public function getTagsForNote($userId, $noteId) {
		$sql = 'SELECT T.id, T.name FROM *PREFIX*quicknotes_tags T ';
		$sql.= 'INNER JOIN *PREFIX*quicknotes_note_tags NT ';
		$sql.= 'ON T.id = NT.tag_id ';
		$sql.= 'WHERE NT.user_id = ? AND NT.note_id = ?';
		return $this->findEntities($sql, [$userId, $noteId]);
	}

	public function getTag($userId, $name) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_tags WHERE user_id = ? AND name = ?';
		return $this->findEntity($sql, [$userId, $name]);
	}

	public function tagExists($userId, $name) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_tags WHERE user_id = ? AND name = ?';
		try {
			return $this->findEntities($sql, [$userId, $name]);
		} catch (DoesNotExistException $e) {
			return false;
		}
		return true;
	}

	public function dropOld () {
		$sql = 'DELETE FROM *PREFIX*quicknotes_tags WHERE ';
		$sql.= 'id NOT IN (SELECT tag_id FROM *PREFIX*quicknotes_note_tags)';
		return $this->execute($sql, []);
	}
}