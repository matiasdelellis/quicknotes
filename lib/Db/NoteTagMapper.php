<?php
namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;
use OCP\AppFramework\Db\DoesNotExistException;

use OCA\QuickNotes\Db\NoteTag;

class NoteTagMapper extends Mapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_note_tags', '\OCA\QuickNotes\Db\NoteTag');
	}

	public function find($id, $userId): NoteTag {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_note_tags WHERE id = ? AND user_id = ?';
		return $this->findEntity($sql, [$id, $userId]);
	}

	public function findAll($userId): array {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_note_tags WHERE user_id = ?';
		return $this->findEntities($sql, [$userId]);
	}

	public function findNoteTag(string $userId, int $noteId, $tagId): NoteTag {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_note_tags WHERE user_id = ? AND note_id = ? AND tag_id = ?';
		return $this->findEntity($sql, [$userId, $noteId, $tagId]);
	}

	/**
	 * @return bool
	 */
	public function noteTagExists(string $userId, int $noteId, int $tagId): bool {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_note_tags WHERE user_id = ? AND note_id = ? AND tag_id = ?';
		try {
			$this->findEntities($sql, [$userId, $noteId, $tagId]);
		} catch (DoesNotExistException $e) {
			return false;
		}
		return true;
	}

}