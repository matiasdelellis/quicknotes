<?php
namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;

use OCP\DB\QueryBuilder\IQueryBuilder;

use OCA\QuickNotes\Db\NoteTag;

class NoteTagMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_note_tags', NoteTag::class);
	}

	public function findNoteTag(string $userId, int $noteId, $tagId): NoteTag {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('tag_id', $qb->createNamedParameter($tagId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * @return bool
	 */
	public function noteTagExists(string $userId, int $noteId, int $tagId): bool {
		try {
			$this->findNoteTag($userId, $noteId, $tagId);
		} catch (DoesNotExistException $e) {
			return false;
		}
		return true;
	}

}