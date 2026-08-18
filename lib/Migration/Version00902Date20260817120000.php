<?php

declare(strict_types=1);

namespace OCA\QuickNotes\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Reminders become personal.
 *
 * They used to be two columns on the note — `reminder_at` and
 * `reminder_notified_at` — which made them the owner's: `notifyDue()`
 * notified `$note->getUserId()`, and the fifteen people a note was shared with
 * got the badge of a date somebody else had picked and no way to pick their
 * own. A reminder is somebody deciding when *they* want to be interrupted.
 *
 * So they move to `quicknotes_note_states`, next to the pin, which is where
 * this app keeps what one user made of one note. One per note and user, which
 * the unique index of that table already enforces.
 *
 * The existing reminders are carried over to the owner of each note here; the
 * next migration drops the columns they came from.
 */
class Version00902Date20260817120000 extends SimpleMigrationStep {

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

		if ($schema->hasTable('quicknotes_note_states')) {
			$table = $schema->getTable('quicknotes_note_states');

			// When this user wants to be reminded of this note, in UTC. Null
			// means they have no reminder on it.
			if (!$table->hasColumn('reminder_at')) {
				$table->addColumn('reminder_at', 'datetime', [
					'notnull' => false,
				]);
			}

			// When the notification for `reminder_at` was sent, so the job
			// does not notify the same person twice. Re-arming clears it.
			if (!$table->hasColumn('reminder_notified_at')) {
				$table->addColumn('reminder_notified_at', 'datetime', [
					'notnull' => false,
				]);
			}

			// The background job scans by this every five minutes.
			if (!$table->hasIndex('qn_state_reminder_idx')) {
				$table->addIndex(['reminder_at'], 'qn_state_reminder_idx');
			}
		}

		return $schema;
	}

	/**
	 * Move the reminder of every note to its owner. The columns this reads
	 * are dropped by the migration right after this one.
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$moved = 0;

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id', 'reminder_at', 'reminder_notified_at')
			->from('quicknotes_notes')
			->where($qb->expr()->isNotNull('reminder_at'));
		$result = $qb->executeQuery();

		while ($row = $result->fetch()) {
			$noteId = (int)$row['id'];
			$userId = (string)$row['user_id'];

			// The owner may already have a row here, from having pinned the
			// note back when 0.9.1 moved the pin.
			$exists = $this->db->getQueryBuilder();
			$exists->select('id')
				->from('quicknotes_note_states')
				->where(
					$exists->expr()->eq('note_id', $exists->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)),
					$exists->expr()->eq('user_id', $exists->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				);
			$statement = $exists->executeQuery();
			$stateId = $statement->fetchOne();
			$statement->closeCursor();

			if ($stateId === false || $stateId === null) {
				$insert = $this->db->getQueryBuilder();
				$insert->insert('quicknotes_note_states')
					->values([
						'note_id' => $insert->createNamedParameter($noteId, IQueryBuilder::PARAM_INT),
						'user_id' => $insert->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
						'pinned' => $insert->createNamedParameter(false, IQueryBuilder::PARAM_BOOL),
						'reminder_at' => $insert->createNamedParameter($row['reminder_at'], IQueryBuilder::PARAM_STR),
						'reminder_notified_at' => $insert->createNamedParameter($row['reminder_notified_at'], IQueryBuilder::PARAM_STR),
					]);
				$insert->executeStatement();
			} else {
				$update = $this->db->getQueryBuilder();
				$update->update('quicknotes_note_states')
					->set('reminder_at', $update->createNamedParameter($row['reminder_at'], IQueryBuilder::PARAM_STR))
					->set('reminder_notified_at', $update->createNamedParameter($row['reminder_notified_at'], IQueryBuilder::PARAM_STR))
					->where($update->expr()->eq('id', $update->createNamedParameter((int)$stateId, IQueryBuilder::PARAM_INT)));
				$update->executeStatement();
			}

			$moved++;
		}
		$result->closeCursor();

		$output->info('Moved ' . $moved . ' reminder(s) to the per-user state table.');
	}

}
