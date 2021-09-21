<?php
namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;

use OCP\DB\QueryBuilder\IQueryBuilder;

use OCA\QuickNotes\Db\Color;

class ColorMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_colors', Color::class);
	}

	public function find(int $id): Color {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	public function findByColor(string $color): Color {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('color', $qb->createNamedParameter($color, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntity($qb);
	}

	public function colorExists(string $color): bool {
		try {
			$this->findByColor($color);
		} catch (DoesNotExistException $e) {
			return false;
		}
		return true;
	}

}