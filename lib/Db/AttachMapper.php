<?php declare(strict_types=1);

namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCA\QuickNotes\Db\Attach;

class AttachMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_attach', Attach::class);
	}

	/**
	 * @param int $id
	 * @param string $userId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @return Attach
	 */
	public function find($id, $userId): Attach {
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
	 * @return Attach[]
	 */
	public function findAll($userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 * @param int $noteId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @return Attach
	 */
	public function findFileAttachFromNote($userId, $noteId, $fileId): Attach {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * @return bool
	 */
	public function fileAttachExists(string $userId, int $noteId, $fileId): bool {
		try {
			$this->findFileAttachFromNote($userId, $noteId, $fileId);
		} catch (DoesNotExistException $e) {
			return false;
		}
		return true;
	}

	/**
	 * Every attachment of a note, whoever attached it.
	 *
	 * The rows carry the user that attached the file, because the file lives
	 * in *their* storage. Listing a note's attachments is therefore not the
	 * same question as listing one user's, and hydrating a note wants this
	 * one: what hangs off the note, for whoever is allowed to see the note.
	 *
	 * @return Attach[]
	 */
	public function findAllFromNote(int $noteId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * One attachment of a note by file id, whoever attached it. This is what
	 * says whether a file is allowed to be served as part of a note.
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 */
	public function findByNoteAndFileId(int $noteId, int $fileId): Attach {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * Drop the attachments one user put on a note. Called when they leave the
	 * note or are unshared from it: their file stops being part of a note they
	 * are no longer part of. The file itself is left alone.
	 */
	public function deleteForUser(string $userId, int $noteId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT))
			)
			->executeStatement();
	}

	/**
	 * Drop every attachment row of a note. Called when the note is destroyed:
	 * the files themselves are left alone, they belong to their users.
	 */
	public function deleteByNoteId(int $noteId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/**
	 * @param string $userId
	 * @param int $noteId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @return Attach[]
	 */
	public function findFromNote($userId, $noteId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntities($qb);
	}

}
