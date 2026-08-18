<?php

declare(strict_types=1);

namespace OCA\QuickNotes\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Drop the reminder columns of the note, now that every reminder belongs to a
 * user in `quicknotes_note_states`.
 *
 * A separate step for the same reason as the share rewrite: the migration
 * before this one copies the data across in `postSchemaChange()`, which runs
 * after its own schema change but before this one. Dropping there would take
 * the source of the copy away first.
 */
class Version00902Date20260817120100 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('quicknotes_notes')) {
			$table = $schema->getTable('quicknotes_notes');

			// The index goes first: it is the one the old background job
			// scanned by, and it has nothing to point at once the column is
			// gone.
			if ($table->hasIndex('qn_notes_reminder_at_idx')) {
				$table->dropIndex('qn_notes_reminder_at_idx');
			}
			if ($table->hasColumn('reminder_at')) {
				$table->dropColumn('reminder_at');
			}
			if ($table->hasColumn('reminder_notified_at')) {
				$table->dropColumn('reminder_notified_at');
			}
		}

		return $schema;
	}

}
