<?php

declare(strict_types=1);

namespace OCA\QuickNotes\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

use OCA\QuickNotes\Db\NoteShare;

/**
 * Sharing, rewritten.
 *
 * `quicknotes_shares` used to be a note id plus two nullable columns,
 * `shared_user` and `shared_group`, the second of which nothing ever wrote.
 * There was no notion of what a recipient was allowed to do, because the
 * answer was always "look at it": every read went through
 * `NoteMapper::find()`, which filters by owner.
 *
 * The table now carries a `share_type` / `share_with` pair and a permission
 * bitmask, using the same values as `OCP\Constants` and `IShare`. The old
 * columns are copied over here and dropped by the next migration, so an
 * upgrade keeps every share that existed, read only, exactly as it behaved.
 *
 * `quicknotes_note_states` is new and holds what a single user made of a note,
 * starting with whether they pinned it. The pin used to be a column on the
 * note itself, which meant the owner pinning a shared note pinned it for
 * everybody. The existing pins are moved to their owners here.
 */
class Version00901Date20260816120000 extends SimpleMigrationStep {

	/** @var IDBConnection */
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('quicknotes_shares')) {
			$table = $schema->getTable('quicknotes_shares');

			// 0 = user, 1 = group, the values of IShare::TYPE_*.
			if (!$table->hasColumn('share_type')) {
				$table->addColumn('share_type', 'smallint', [
					'notnull' => true,
					'default' => NoteShare::TYPE_USER,
				]);
			}

			// The uid or the gid, depending on share_type. 200 like every
			// other user id column of this app.
			//
			// Nullable, and so are the two below: a column added to a table
			// that already exists cannot be NotNull with an empty string as
			// its default, since on Oracle the two are the same value and the
			// existing rows would need one. `ensureOracleConstraints()` in the
			// server refuses the migration outright over it.
			if (!$table->hasColumn('share_with')) {
				$table->addColumn('share_with', 'string', [
					'notnull' => false,
					'length' => 200,
					'default' => '',
				]);
			}

			// Bitmask of NoteShare::PERMISSION_*. Read only by default, which
			// is what every share made until now was.
			if (!$table->hasColumn('permissions')) {
				$table->addColumn('permissions', 'smallint', [
					'notnull' => true,
					'default' => NoteShare::PERMISSIONS_DEFAULT,
				]);
			}

			// Who owns the note, and who created this particular share. They
			// differ as soon as a reshare is involved, and keeping both is
			// what lets the dialog say where a share came from.
			if (!$table->hasColumn('uid_owner')) {
				$table->addColumn('uid_owner', 'string', [
					'notnull' => false,
					'length' => 200,
					'default' => '',
				]);
			}

			if (!$table->hasColumn('uid_initiator')) {
				$table->addColumn('uid_initiator', 'string', [
					'notnull' => false,
					'length' => 200,
					'default' => '',
				]);
			}

			if (!$table->hasColumn('created_at')) {
				$table->addColumn('created_at', 'bigint', [
					'notnull' => true,
					'default' => 0,
				]);
			}

			// Every note lists its shares when it is hydrated, and every
			// listing of notes resolves the shares pointing at the user.
			// Neither had an index to work with until now.
			if (!$table->hasIndex('qn_shares_note_idx')) {
				$table->addIndex(['note_id'], 'qn_shares_note_idx');
			}
			if (!$table->hasIndex('qn_shares_recipient_idx')) {
				$table->addIndex(['share_type', 'share_with'], 'qn_shares_recipient_idx');
			}
		}

		if (!$schema->hasTable('quicknotes_note_states')) {
			$table = $schema->createTable('quicknotes_note_states');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('note_id', 'bigint', [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 200,
				'default' => '',
			]);
			$table->addColumn('pinned', 'boolean', [
				'notnull' => false,
				'default' => false,
			]);
			$table->setPrimaryKey(['id']);
			// One row per note and user, and the lookup is always by both.
			$table->addUniqueIndex(['note_id', 'user_id'], 'qn_state_note_user_idx');
			$table->addIndex(['user_id'], 'qn_state_user_idx');
		}

		return $schema;
	}

	/**
	 * Carry the data of the old shape over to the new one. The columns it
	 * reads are dropped by the migration right after this one, so this is the
	 * only chance to do it.
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->migrateShares($output);
		$this->migratePins($output);
	}

	private function migrateShares(IOutput $output): void {
		// Owner of every note, to fill uid_owner / uid_initiator with. A share
		// made before this migration was necessarily made by the owner: there
		// was no way to reshare.
		$owners = [];
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id')->from('quicknotes_notes');
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$owners[(int)$row['id']] = (string)$row['user_id'];
		}
		$result->closeCursor();

		$now = time();
		$migrated = 0;

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'note_id', 'shared_user', 'shared_group')
			->from('quicknotes_shares');
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$sharedUser = (string)($row['shared_user'] ?? '');
			$sharedGroup = (string)($row['shared_group'] ?? '');

			if ($sharedUser !== '') {
				$shareType = NoteShare::TYPE_USER;
				$shareWith = $sharedUser;
			} elseif ($sharedGroup !== '') {
				$shareType = NoteShare::TYPE_GROUP;
				$shareWith = $sharedGroup;
			} else {
				// A share with nobody. It could never have been read, so it
				// is not worth carrying over.
				continue;
			}

			$noteId = (int)$row['note_id'];
			$owner = $owners[$noteId] ?? '';

			$update = $this->db->getQueryBuilder();
			$update->update('quicknotes_shares')
				->set('share_type', $update->createNamedParameter($shareType, IQueryBuilder::PARAM_INT))
				->set('share_with', $update->createNamedParameter($shareWith, IQueryBuilder::PARAM_STR))
				->set('permissions', $update->createNamedParameter(NoteShare::PERMISSIONS_DEFAULT, IQueryBuilder::PARAM_INT))
				->set('uid_owner', $update->createNamedParameter($owner, IQueryBuilder::PARAM_STR))
				->set('uid_initiator', $update->createNamedParameter($owner, IQueryBuilder::PARAM_STR))
				->set('created_at', $update->createNamedParameter($now, IQueryBuilder::PARAM_INT))
				->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)));
			$update->executeStatement();
			$migrated++;
		}
		$result->closeCursor();

		$output->info('Migrated ' . $migrated . ' note share(s) to the new share model.');
	}

	private function migratePins(IOutput $output): void {
		$migrated = 0;

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id')
			->from('quicknotes_notes')
			->where($qb->expr()->eq('pinned', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$insert = $this->db->getQueryBuilder();
			$insert->insert('quicknotes_note_states')
				->values([
					'note_id' => $insert->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT),
					'user_id' => $insert->createNamedParameter((string)$row['user_id'], IQueryBuilder::PARAM_STR),
					'pinned' => $insert->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
				]);
			$insert->executeStatement();
			$migrated++;
		}
		$result->closeCursor();

		$output->info('Moved ' . $migrated . ' pinned note(s) to the per-user state table.');
	}

}
