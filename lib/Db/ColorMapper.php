<?php
namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;
use OCP\AppFramework\Db\DoesNotExistException;

use OCA\QuickNotes\Db\Color;

class ColorMapper extends Mapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_colors', '\OCA\QuickNotes\Db\Color');
	}

	public function find($id): Color {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_colors WHERE id = ?';
		return $this->findEntity($sql, [$id]);
	}

	public function findAll(): array {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_colors';
		return $this->findEntities($sql, []);
	}

	public function findByColor(string $color): Color {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_colors WHERE color = ?';
		return $this->findEntity($sql, [$color]);
	}

	public function colorExists(string $color): bool {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_colors WHERE color = ?';
		try {
			$this->findEntity($sql, [$color]);
		} catch (DoesNotExistException $e) {
			return false;
		}
		return true;
	}

}