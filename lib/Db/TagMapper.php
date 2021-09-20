<?php
namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;
use OCP\AppFramework\Db\DoesNotExistException;

use OCA\QuickNotes\Db\Tag;

class TagMapper extends Mapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_tags', '\OCA\QuickNotes\Db\Tag');
	}

	public function find($id, $userId): Tag {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_tags WHERE id = ? AND user_id = ?';
		return $this->findEntity($sql, [$id, $userId]);
	}

	public function findAll($userId): array {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_tags WHERE user_id = ?';
		return $this->findEntities($sql, [$userId]);
	}

	public function getTagsForNote(string $userId, int $noteId): array {
		$sql = 'SELECT T.id, T.name FROM *PREFIX*quicknotes_tags T ';
		$sql.= 'INNER JOIN *PREFIX*quicknotes_note_tags NT ';
		$sql.= 'ON T.id = NT.tag_id ';
		$sql.= 'WHERE NT.user_id = ? AND NT.note_id = ?';
		return $this->findEntities($sql, [$userId, $noteId]);
	}

	public function getTag(string $userId, $name): Tag {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_tags WHERE user_id = ? AND name = ?';
		return $this->findEntity($sql, [$userId, $name]);
	}

	/**
	 * @return bool
	 */
	public function tagExists(string $userId, $name): bool {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_tags WHERE user_id = ? AND name = ?';
		try {
			$this->findEntities($sql, [$userId, $name]);
		} catch (DoesNotExistException $e) {
			return false;
		}
		return true;
	}

	public function dropOld () {
		$sql = 'DELETE FROM *PREFIX*quicknotes_tags WHERE ';
		$sql.= 'id NOT IN (SELECT tag_id FROM *PREFIX*quicknotes_note_tags)';
		$this->execute($sql, []);
	}
}