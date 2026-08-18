<?php
/*
 * @copyright 2020-2026 Matias De lellis <mati86dl@gmail.com>
 *
 * @author 2020 Matias De lellis <mati86dl@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCA\QuickNotes\Db\NoteShare;

/**
 * @method NoteShare insert(NoteShare $share)
 * @method NoteShare update(NoteShare $share)
 * @method NoteShare delete(NoteShare $share)
 */
class NoteShareMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_shares', NoteShare::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function find(int $id): NoteShare {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * Every share of a note, whoever it is with. This is what the owner sees
	 * in the share dialog, so it is ordered the way it is displayed.
	 *
	 * @return NoteShare[]
	 */
	public function findByNoteId(int $noteId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('share_type', 'ASC')
			->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * The shares of several notes at once, keyed by note id.
	 *
	 * `getAll()` hydrates every note of the user, and asking for the shares of
	 * each one separately is a query per note. Chunked because a user with a
	 * few thousand notes would otherwise build a single statement with a few
	 * thousand parameters, which SQLite and SQL Server refuse.
	 *
	 * @param int[] $noteIds
	 * @return array<int, NoteShare[]>
	 */
	public function findByNoteIds(array $noteIds): array {
		/** @var array<int, NoteShare[]> $shares */
		$shares = [];
		foreach (array_chunk(array_values(array_unique($noteIds)), 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where(
					$qb->expr()->in('note_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY))
				)
				->orderBy('share_type', 'ASC')
				->addOrderBy('id', 'ASC');
			foreach ($this->findEntities($qb) as $share) {
				$noteId = (int)$share->getNoteId();
				if (!isset($shares[$noteId])) {
					$shares[$noteId] = [];
				}
				$shares[$noteId][] = $share;
			}
		}
		return $shares;
	}

	/**
	 * The shares that give a user access to a note: the one made with them
	 * directly, plus the ones made with any group they belong to.
	 *
	 * @param string[] $groupIds
	 * @return NoteShare[]
	 */
	public function findByNoteAndRecipient(int $noteId, string $userId, array $groupIds = []): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)))
			->andWhere($this->recipientExpression($qb, $userId, $groupIds));
		return $this->findEntities($qb);
	}

	/**
	 * Every share that points at a user, directly or through a group. The
	 * notes shared *with* them, in other words.
	 *
	 * @param string[] $groupIds
	 * @return NoteShare[]
	 */
	public function findByRecipient(string $userId, array $groupIds = []): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($this->recipientExpression($qb, $userId, $groupIds));
		return $this->findEntities($qb);
	}

	/**
	 * The share of a note with one precise recipient, used to keep a note from
	 * being shared twice with the same user or group.
	 *
	 * @throws DoesNotExistException
	 */
	public function findByNoteAndTarget(int $noteId, int $shareType, string $shareWith): NoteShare {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('share_type', $qb->createNamedParameter($shareType, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('share_with', $qb->createNamedParameter($shareWith, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntity($qb);
	}

	public function existsByNoteAndTarget(int $noteId, int $shareType, string $shareWith): bool {
		try {
			$this->findByNoteAndTarget($noteId, $shareType, $shareWith);
		} catch (DoesNotExistException $e) {
			return false;
		}
		return true;
	}

	/**
	 * Drop every share of a note. Called when the note itself goes.
	 */
	public function deleteByNoteId(int $noteId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/**
	 * A user leaving a note that was shared with them personally.
	 *
	 * Only a user share can be left: dropping a group share would take the
	 * note away from everybody else in the group, which is not the caller's
	 * to decide.
	 */
	public function deleteUserShare(int $noteId, string $userId): bool {
		try {
			$share = $this->findByNoteAndTarget($noteId, NoteShare::TYPE_USER, $userId);
		} catch (DoesNotExistException $e) {
			return false;
		}
		$this->delete($share);
		return true;
	}

	/**
	 * `share_with` matches the user, or one of the groups they are in.
	 *
	 * @param string[] $groupIds
	 */
	private function recipientExpression(IQueryBuilder $qb, string $userId, array $groupIds) {
		$userMatch = $qb->expr()->andX(
			$qb->expr()->eq('share_type', $qb->createNamedParameter(NoteShare::TYPE_USER, IQueryBuilder::PARAM_INT)),
			$qb->expr()->eq('share_with', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
		);

		if (count($groupIds) === 0) {
			return $userMatch;
		}

		$groupMatch = $qb->expr()->andX(
			$qb->expr()->eq('share_type', $qb->createNamedParameter(NoteShare::TYPE_GROUP, IQueryBuilder::PARAM_INT)),
			$qb->expr()->in('share_with', $qb->createNamedParameter($groupIds, IQueryBuilder::PARAM_STR_ARRAY))
		);

		return $qb->expr()->orX($userMatch, $groupMatch);
	}

}
