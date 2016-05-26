<?php
namespace OCA\QuickNotes\Db;

use OCP\IDb;
use OCP\AppFramework\Db\Mapper;
use OCP\AppFramework\Db\DoesNotExistException;

class ColorMapper extends Mapper {

	public function __construct(IDb $db) {
		parent::__construct($db, 'quicknotes_colors', '\OCA\QuickNotes\Db\Color');
	}

	public function find($id) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_colors WHERE id = ?';
		return $this->findEntity($sql, [$id]);
	}

	public function findAll() {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_colors';
		return $this->findEntities($sql, []);
	}

	public function findByColor($color) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_colors WHERE color = ?';
		return $this->findEntity($sql, [$color]);
	}

	public function colorExists($color) {
		$sql = 'SELECT * FROM *PREFIX*quicknotes_colors WHERE color = ?';
		try {
			$this->findEntity($sql, [$color]);
		} catch (DoesNotExistException $e) {
			return false;
		}
		return true;
	}

}