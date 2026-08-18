<?php

declare(strict_types=1);

namespace OCA\QuickNotes\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Drop `quicknotes_notes.archived_at`, now that archiving belongs to a user
 * and lives in `quicknotes_note_states`.
 *
 * A separate step for the same reason as the two rewrites before it: the
 * migration that copies the data runs its `postSchemaChange()` after its own
 * schema change but before this one.
 *
 * `deleted_at` stays where it is on purpose. The trash is not a view of one
 * user, it is the note being on its way out of existence, and that is the
 * owner's to decide.
 */
class Version00903Date20260817140100 extends SimpleMigrationStep {

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
			if ($table->hasColumn('archived_at')) {
				$table->dropColumn('archived_at');
			}
		}

		return $schema;
	}

}
