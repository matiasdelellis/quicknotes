<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setColor(string $color)
 * @method string getColor()
 */
class Color extends Entity implements JsonSerializable {

	protected $color;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'color' => $this->color
		];
	}

}