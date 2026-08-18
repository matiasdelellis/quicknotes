<?php

declare(strict_types=1);

namespace OCA\QuickNotes\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Drop what the share rewrite replaced.
 *
 * A separate step from the migration that adds the new columns, because that
 * one copies the data across in `postSchemaChange()` — which runs after its
 * own schema change, but before this one. Dropping in the same step would take
 * the source of the copy away before the copy happened.
 *
 * `quicknotes_notes.pinned` goes for the same reason: `quicknotes_note_states`
 * now holds the pin of each user, and leaving the column behind would leave
 * two answers to the same question.
 */
class Version00901Date20260816120100 extends SimpleMigrationStep {

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
			if ($table->hasColumn('shared_user')) {
				$table->dropColumn('shared_user');
			}
			if ($table->hasColumn('shared_group')) {
				$table->dropColumn('shared_group');
			}
		}

		if ($schema->hasTable('quicknotes_notes')) {
			$table = $schema->getTable('quicknotes_notes');
			if ($table->hasColumn('pinned')) {
				$table->dropColumn('pinned');
			}
		}

		return $schema;
	}

}
