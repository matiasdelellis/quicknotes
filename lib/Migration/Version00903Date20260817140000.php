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
 * Archiving becomes personal too.
 *
 * It was a column on the note, so it was the owner's: the people a note was
 * shared with saw it land in *their* archive because somebody else had tidied
 * up, and had no way of tidying it away themselves. That last part is the one
 * that hurts on a note shared with a group, which the recipient cannot leave
 * either — the share is not theirs to drop — so there was simply no way to get
 * it out of the grid.
 *
 * Archiving is "get this out of my list", which is a decision of whoever is
 * looking, so it joins the pin, the tags and the reminder in
 * `quicknotes_note_states`. The trash does not: `deleted_at` stays on the note
 * because it is about whether the note goes on existing at all, and that
 * belongs to its owner.
 *
 * The existing archived notes are carried over to their owners here; the next
 * migration drops the column they came from.
 */
class Version00903Date20260817140000 extends SimpleMigrationStep {

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

			// When this user archived this note, in UTC. Null means they did
			// not, whatever anybody else did with it.
			if (!$table->hasColumn('archived_at')) {
				$table->addColumn('archived_at', 'datetime', [
					'notnull' => false,
				]);
			}
		}

		return $schema;
	}

	/**
	 * Move the archive state of every note to its owner. The column this reads
	 * is dropped by the migration right after this one.
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$moved = 0;

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id', 'archived_at')
			->from('quicknotes_notes')
			->where($qb->expr()->isNotNull('archived_at'));
		$result = $qb->executeQuery();

		while ($row = $result->fetch()) {
			$noteId = (int)$row['id'];
			$userId = (string)$row['user_id'];

			// The owner may already have a row here, from the pin or a reminder.
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
						'archived_at' => $insert->createNamedParameter($row['archived_at'], IQueryBuilder::PARAM_STR),
					]);
				$insert->executeStatement();
			} else {
				$update = $this->db->getQueryBuilder();
				$update->update('quicknotes_note_states')
					->set('archived_at', $update->createNamedParameter($row['archived_at'], IQueryBuilder::PARAM_STR))
					->where($update->expr()->eq('id', $update->createNamedParameter((int)$stateId, IQueryBuilder::PARAM_INT)));
				$update->executeStatement();
			}

			$moved++;
		}
		$result->closeCursor();

		$output->info('Moved ' . $moved . ' archived note(s) to the per-user state table.');
	}

}
