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

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * One share of one note, with one user or one group.
 *
 * Until 0.9.0 this was a pair of nullable columns — `shared_user` and
 * `shared_group`, the second one never written by anything — and no notion of
 * what the recipient was allowed to do: every share was read only, enforced by
 * the fact that nothing but `NoteMapper::find()` (which filters by owner) could
 * reach a note at all.
 *
 * It is now modelled the way the server models its own shares: a `share_type`
 * plus a `share_with`, and a bitmask of permissions using the very values of
 * `OCP\Constants`, so the numbers mean the same thing here as everywhere else
 * in Nextcloud. They are redeclared instead of imported to keep the entity free
 * of a dependency on the server for what ends up in the database.
 *
 * @method int getNoteId()
 * @method void setNoteId(int $noteId)
 * @method int getShareType()
 * @method void setShareType(int $shareType)
 * @method string getShareWith()
 * @method void setShareWith(string $shareWith)
 * @method int getPermissions()
 * @method void setPermissions(int $permissions)
 * @method string getUidOwner()
 * @method void setUidOwner(string $uidOwner)
 * @method string getUidInitiator()
 * @method void setUidInitiator(string $uidInitiator)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class NoteShare extends Entity implements JsonSerializable {

	/** Shared with a single user. Same value as `IShare::TYPE_USER`. */
	public const TYPE_USER = 0;

	/** Shared with every member of a group. Same as `IShare::TYPE_GROUP`. */
	public const TYPE_GROUP = 1;

	/** See the note. Same value as `Constants::PERMISSION_READ`. */
	public const PERMISSION_READ = 1;

	/** Edit title and content. Same as `Constants::PERMISSION_UPDATE`. */
	public const PERMISSION_UPDATE = 2;

	/** Pass the note on to somebody else. Same as `Constants::PERMISSION_SHARE`. */
	public const PERMISSION_SHARE = 16;

	/** What a share can hold at most, and what the owner of a note always has. */
	public const PERMISSIONS_ALL = self::PERMISSION_READ
	                            | self::PERMISSION_UPDATE
	                            | self::PERMISSION_SHARE;

	/**
	 * What a new share gets when the caller does not say. Read only, which is
	 * what every share created before 0.9.1 was, so sharing keeps behaving the
	 * way it used to unless the user asks for more.
	 */
	public const PERMISSIONS_DEFAULT = self::PERMISSION_READ;

	// Db Entity
	protected $noteId;
	protected $shareType;
	protected $shareWith;
	protected $permissions;
	protected $uidOwner;
	protected $uidInitiator;
	protected $createdAt;

	// Not columns: filled in from the user and group managers when the share
	// is handed to a client, which is the only place a display name is of any
	// use. Declared as properties because writing to an undeclared one is
	// deprecated since PHP 8.2.
	protected $displayName;
	protected $ownerDisplayName;

	public function __construct() {
		$this->addType('noteId', 'integer');
		$this->addType('shareType', 'integer');
		$this->addType('permissions', 'integer');
		$this->addType('createdAt', 'integer');
	}

	public function setDisplayName(string $displayName): void {
		$this->displayName = $displayName;
	}

	public function getDisplayName(): ?string {
		return $this->displayName;
	}

	public function setOwnerDisplayName(string $ownerDisplayName): void {
		$this->ownerDisplayName = $ownerDisplayName;
	}

	public function getOwnerDisplayName(): ?string {
		return $this->ownerDisplayName;
	}

	public function canEdit(): bool {
		return ((int)$this->permissions & self::PERMISSION_UPDATE) !== 0;
	}

	public function canReshare(): bool {
		return ((int)$this->permissions & self::PERMISSION_SHARE) !== 0;
	}

	public function isGroupShare(): bool {
		return (int)$this->shareType === self::TYPE_GROUP;
	}

	/**
	 * Whether a permission bitmask is something this share is allowed to hand
	 * out. Read is implicit — a share that grants nothing is not a share — and
	 * nothing above what the share itself holds can be passed on.
	 */
	public static function arePermissionsValid(int $permissions): bool {
		if (($permissions & self::PERMISSION_READ) === 0) {
			return false;
		}
		return ($permissions & ~self::PERMISSIONS_ALL) === 0;
	}

	public function jsonSerialize(): array {
		return [
			'id'               => (int)$this->id,
			'noteId'           => (int)$this->noteId,
			'shareType'        => (int)$this->shareType,
			// The templates cannot compare numbers, and the difference
			// between a person and a group is worth showing in the badge.
			'isGroup'          => $this->isGroupShare(),
			'shareWith'        => $this->shareWith,
			'displayName'      => $this->displayName ?? $this->shareWith,
			'permissions'      => (int)$this->permissions,
			'canEdit'          => $this->canEdit(),
			'canReshare'       => $this->canReshare(),
			'uidOwner'         => $this->uidOwner,
			'ownerDisplayName' => $this->ownerDisplayName ?? $this->uidOwner,
			'uidInitiator'     => $this->uidInitiator,
			'createdAt'        => (int)$this->createdAt,
		];
	}

}
