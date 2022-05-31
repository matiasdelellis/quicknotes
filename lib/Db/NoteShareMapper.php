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

	public function findSharesForUserId(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('shared_user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntities($qb);
	}

	public function findSharesForGroupId(string $groupId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('shared_group', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntities($qb);
	}

	public function findSharesByNoteIsAndSharedUser(int $noteId, string $userId): NoteShare {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('shared_user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
			);
		return $this->findEntity($qb);
	}

	public function findSharesByNoteIdAndGroupId(int $noteId, string $groupId): NoteShare {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('shared_group', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_STR)),
			);
		return $this->findEntity($qb);
	}

	public function findSharesForNoteId(int $noteId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntities($qb);
	}

	public function forgetSharesByNoteId(int $noteId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('note_id', $qb->createNamedParameter($noteId)))
			->execute();
	}

	public function forgetShareByNoteIdAndSharedUser(int $noteId, string $userId) {
		try {
			$noteShare = $this->findSharesByNoteIsAndSharedUser($noteId, $userId);
		} catch (DoesNotExistException $e) {
			return false;
		}
		$this->delete($noteShare);
		return true;
	}

	/**
	 * @return bool
	 */
	public function existsByNoteAndSharedUser(int $noteId, string $userId) {
		try {
			$this->findSharesByNoteIsAndSharedUser($noteId, $userId);
		} catch (DoesNotExistException $e) {
			return false;
		}
		return true;
	}
}
