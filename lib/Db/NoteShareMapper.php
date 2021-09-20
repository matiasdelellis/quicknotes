<?php
namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCA\QuickNotes\Db\NoteShare;

class NoteShareMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_shares', NoteShare::class);
	}

	public function findForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('shared_user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntities($qb);
	}

	public function findForGroup(string $groupId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('shared_group', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntities($qb);
	}

	public function findByNoteAndUser(int $noteId, string $userId): NoteShare {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('shared_user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
			);
		return $this->findEntity($qb);
	}

	public function findByNoteAndGroup(int $noteId, string $groupId): NoteShare {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('shared_group', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_STR)),
			);
		return $this->findEntity($qb);
	}

	public function getSharesForNote(int $noteId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntities($qb);
	}

	public function deleteByNoteId(int $noteId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('note_id', $qb->createNamedParameter($noteId)))
			->execute();
	}

	/**
	 * @return bool
	 */
	public function existsByNoteAndUser(int $noteId, string $userId) {
		try {
			$this->findByNoteAndUser($noteId, $userId);
		} catch (DoesNotExistException $e) {
			return false;
		}
		return true;
	}
}
