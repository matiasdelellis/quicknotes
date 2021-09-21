<?php
namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCA\QuickNotes\Db\Tag;

class TagMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_tags', Tag::class);
	}

	public function getTagsForNote(string $userId, int $noteId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('T.id', 'T.name')
			->from($this->getTableName(), 'T')
			->innerJoin('T', 'quicknotes_note_tags' ,'NT', $qb->expr()->eq('T.id', 'NT.tag_id'))
			->where($qb->expr()->eq('NT.user_id', $qb->createParameter('user_id')))
			->andWhere($qb->expr()->eq('NT.note_id', $qb->createParameter('note_id')))
			->setParameter('user_id', $userId)
			->setParameter('note_id', $noteId);
		return $this->findEntities($qb);
	}

	public function getTag(string $userId, string $name): Tag {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('name', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntity($qb);
	}

	/**
	 * @return bool
	 */
	public function tagExists(string $userId, string $name): bool {
		try {
			$this->getTag($userId, $name);
		} catch (DoesNotExistException $e) {
			return false;
		}
		return true;
	}

	public function dropOld (): void {
		$sub = $this->db->getQueryBuilder();
		$sub->select('tag_id')
			->from('quicknotes_note_tags');

		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where('id NOT IN (' . $sub->getSQL() . ')')
			->execute();
	}
}