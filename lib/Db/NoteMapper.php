<?php declare(strict_types=1);

namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * @method Note update(Note $note)
 */
class NoteMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_notes', Note::class);
	}

	/**
	 * @param int $id
	 * @param string $userId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @return Note
	 */
	public function find($id, $userId): Note {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @param string $queryStr
	 * @param int|null $offset
	 * @param int|null $limit
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 *
	 * @return Note[]
	 */
	public function findLike($userId, $queryStr, ?int $offset = null, ?int $limit = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->like($qb->func()->lower('title'), $qb->createParameter('query')),
					$qb->expr()->like($qb->func()->lower('content'), $qb->createParameter('query'))
				)
			);

		$query = '%' . $this->db->escapeLikeParameter(strtolower($queryStr)) . '%';
		$qb->setParameter('query', $query);

		$qb->setFirstResult($offset);
		$qb->setMaxResults($limit);

		return $this->findEntities($qb);
	}

	/**
	 * @param int $id
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @return Note
	 */
	public function findShared($id): Note {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * @return Note[]
	 */
	public function findAll(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntities($qb);
	}

	/**
	 * @return int
	 */
	public function colorIdCount(int $colorid): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('color_id', $qb->createNamedParameter($colorid, IQueryBuilder::PARAM_INT))
			);
		return count($this->findEntities($qb));
	}

	/**
	 * Update only the archive / soft-delete columns of a note in a
	 * single SQL statement. Using the QueryBuilder directly avoids
	 * the QBMapper / Entity update path, which would otherwise need
	 * the columns to be registered via addType() and would re-write
	 * every other field on the row.
	 *
	 * Pass `null` to clear a column.
	 *
	 * @param int    $id
	 * @param string|null $archivedAt
	 * @param string|null $deletedAt
	 *
	 * @return int the number of affected rows
	 */
	public function updateArchiveState(int $id, ?string $archivedAt, ?string $deletedAt): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('archived_at', $qb->createNamedParameter($archivedAt, IQueryBuilder::PARAM_STR))
			->set('deleted_at',  $qb->createNamedParameter($deletedAt,  IQueryBuilder::PARAM_STR))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * Clear both archive / soft-delete columns. Convenience wrapper
	 * kept for symmetry with updateArchiveState.
	 */
	public function clearArchiveState(int $id): int {
		return $this->updateArchiveState($id, null, null);
	}

	/**
	 * Find notes that have been soft-deleted (have a `deleted_at`)
	 * strictly before the given cutoff. Used by the hourly
	 * `PurgeOldTrashJob` to hard-delete notes the user left in the
	 * trash for longer than the retention period.
	 *
	 * @return Note[]
	 */
	public function findOldDeletedNotes(\DateTime $cutoff): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->isNotNull('deleted_at'),
				$qb->expr()->lt('deleted_at', $qb->createNamedParameter(
					$cutoff->format('Y-m-d H:i:s'),
					IQueryBuilder::PARAM_STR
				))
			);
		return $this->findEntities($qb);
	}

	/**
	 * Every note of a user that carries a reminder, whether it has already
	 * fired or not. Notes in the trash are left out, the same way
	 * findDueReminders() leaves them out.
	 *
	 * Feeds the virtual calendar, so it is ordered by the reminder date to
	 * save the caller a sort.
	 *
	 * @return Note[]
	 */
	public function findWithReminders(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->isNotNull('reminder_at'),
				$qb->expr()->isNull('deleted_at')
			)
			->orderBy('reminder_at', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Arm, move or clear the reminder of a note. Uses the QueryBuilder
	 * directly for the same reason as updateArchiveState().
	 *
	 * Writing a reminder always clears `reminder_notified_at`, so moving a
	 * reminder that already fired arms it again instead of staying silent.
	 *
	 * @param int         $id
	 * @param string|null $reminderAt UTC 'Y-m-d H:i:s', or null to cancel
	 *
	 * @return int the number of affected rows
	 */
	public function updateReminder(int $id, ?string $reminderAt): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('reminder_at', $qb->createNamedParameter($reminderAt, IQueryBuilder::PARAM_STR))
			->set('reminder_notified_at', $qb->createNamedParameter(null, IQueryBuilder::PARAM_STR))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * Record that the reminder notification for this note went out.
	 *
	 * @return int the number of affected rows
	 */
	public function markReminderNotified(int $id, \DateTime $when): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('reminder_notified_at', $qb->createNamedParameter(
				$when->format('Y-m-d H:i:s'),
				IQueryBuilder::PARAM_STR
			))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * Find notes whose reminder is due and has not been notified yet. Used
	 * by `NoteReminderJob`.
	 *
	 * Notes in the trash are skipped: sending to the trash cancels the
	 * reminder. Archived notes are *not* skipped — archiving only takes a
	 * note out of the active list, it is not a way to cancel a reminder.
	 *
	 * @return Note[]
	 */
	public function findDueReminders(\DateTime $now): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->isNotNull('reminder_at'),
				$qb->expr()->isNull('reminder_notified_at'),
				$qb->expr()->isNull('deleted_at'),
				$qb->expr()->lte('reminder_at', $qb->createNamedParameter(
					$now->format('Y-m-d H:i:s'),
					IQueryBuilder::PARAM_STR
				))
			);
		return $this->findEntities($qb);
	}

}
