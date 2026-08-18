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
	 * Notes matching a search term, for the search provider.
	 *
	 * `$sharedIds` are the notes somebody else shared with this user: they are
	 * reachable from the app, so they are searchable too. Trashed notes are
	 * left out — the trash is a place things are on their way out of, not
	 * something to be offered as a result.
	 *
	 * @param string $userId
	 * @param string $queryStr
	 * @param int[] $sharedIds ids of the notes shared with the user
	 * @param int|null $offset
	 * @param int|null $limit
	 *
	 * @return Note[]
	 */
	public function findLike($userId, $queryStr, array $sharedIds = [], ?int $offset = null, ?int $limit = null): array {
		$qb = $this->db->getQueryBuilder();

		$reachable = $qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR));
		if (count($sharedIds) > 0) {
			$reachable = $qb->expr()->orX(
				$reachable,
				$qb->expr()->in('id', $qb->createNamedParameter($sharedIds, IQueryBuilder::PARAM_INT_ARRAY))
			);
		}

		$qb->select('*')
			->from($this->tableName)
			->where($reachable)
			->andWhere($qb->expr()->isNull('deleted_at'))
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
	 * Several notes at once, whoever owns them. Feeds the list of notes
	 * shared with a user, which is a set of ids resolved from the shares.
	 *
	 * Chunked for the same reason as `NoteShareMapper::findByNoteIds()`.
	 *
	 * @param int[] $ids
	 * @return Note[]
	 */
	public function findByIds(array $ids): array {
		$notes = [];
		foreach (array_chunk(array_values(array_unique($ids)), 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->tableName)
				->where(
					$qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY))
				);
			$notes = array_merge($notes, $this->findEntities($qb));
		}
		return $notes;
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
	 * Send a note to the trash, or bring it back, in a single SQL statement.
	 * Using the QueryBuilder directly avoids the QBMapper / Entity update
	 * path, which would otherwise need the column to be registered via
	 * addType() and would re-write every other field on the row.
	 *
	 * Pass `null` to restore.
	 *
	 * The archive state used to be set here too, in the same call. It is per
	 * user since 0.9.3 and lives in `quicknotes_note_states`; the trash is
	 * still the note's, and the owner's.
	 *
	 * @return int the number of affected rows
	 */
	public function updateDeletedAt(int $id, ?string $deletedAt): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('deleted_at', $qb->createNamedParameter($deletedAt, IQueryBuilder::PARAM_STR))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	/**
	 * The notes of a user that are sitting in the trash.
	 *
	 * The trash is the note's, and the owner's: a note somebody else shared
	 * with you is never in yours, so this asks by `user_id` and not through
	 * the shares.
	 *
	 * @return Note[]
	 */
	public function findDeletedByUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->isNotNull('deleted_at')
			)
			->orderBy('deleted_at', 'ASC');
		return $this->findEntities($qb);
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

	/*
	 * The reminders used to live here, on two columns of the note, and so did
	 * findWithReminders() / findDueReminders() / updateReminder() /
	 * markReminderNotified(). They belong to a user rather than to a note
	 * since 0.9.2 and moved to NoteStateMapper with the columns.
	 */

}
