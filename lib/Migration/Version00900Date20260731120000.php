<?php

declare(strict_types=1);

namespace OCA\QuickNotes\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version00900Date20260731120000 extends SimpleMigrationStep {

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

			// When the reminder is due, in UTC. Null means the note has no
			// reminder at all.
			if (!$table->hasColumn('reminder_at')) {
				$table->addColumn('reminder_at', 'datetime', [
					'notnull' => false,
				]);
			}

			// When the notification for `reminder_at` was actually sent, so
			// `NoteReminderJob` does not notify the same note on every run.
			// Re-arming a reminder clears this again.
			if (!$table->hasColumn('reminder_notified_at')) {
				$table->addColumn('reminder_notified_at', 'datetime', [
					'notnull' => false,
				]);
			}

			// The background job scans by `reminder_at` every few minutes.
			if (!$table->hasIndex('qn_notes_reminder_at_idx')) {
				$table->addIndex(['reminder_at'], 'qn_notes_reminder_at_idx');
			}
		}

		return $schema;
	}

}
