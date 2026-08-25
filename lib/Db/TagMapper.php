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
				$qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR))
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

	/**
	 * The names of the tags one user put on several notes, keyed by note id.
	 *
	 * The search provider matches against these — a note's tags are as much
	 * part of what it says as its title — and one query for the whole batch is
	 * cheaper than one per note.
	 *
	 * @param int[] $noteIds
	 * @return array<int, string[]>
	 */
	public function getTagNamesForNotes(string $userId, array $noteIds): array {
		$tags = [];
		foreach (array_chunk(array_values(array_unique($noteIds)), 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('NT.note_id', 'T.name')
				->from('quicknotes_tags', 'T')
				->innerJoin('T', 'quicknotes_note_tags', 'NT', $qb->expr()->eq('T.id', 'NT.tag_id'))
				->where($qb->expr()->eq('NT.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->in('NT.note_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));

			$result = $qb->executeQuery();
			while ($row = $result->fetch()) {
				$tags[(int)$row['note_id']][] = (string)$row['name'];
			}
			$result->closeCursor();
		}
		return $tags;
	}

	public function dropOld (): void {
		$sub = $this->db->getQueryBuilder();
		$sub->select('tag_id')
			->from('quicknotes_note_tags');

		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where('id NOT IN (' . $sub->getSQL() . ')')
			->executeStatement();
	}
}