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

	public function findNoteTag(string $userId, int $noteId, int $tagId): NoteTag {
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
	 * Every tag relation of a note, whoever set it.
	 *
	 * Tags are personal — the relation carries the `user_id` of whoever tagged
	 * the note, not of the owner — so a shared note can hold rows of several
	 * users at once, and destroying it has to take all of them.
	 */
	public function deleteByNoteId(int $noteId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/**
	 * The tags one user set on a note. Called when they leave a share: their
	 * own organisation of a note they no longer see is of no use to anybody.
	 */
	public function deleteForUser(string $userId, int $noteId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT))
			)
			->executeStatement();
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