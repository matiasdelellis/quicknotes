<?php

declare(strict_types=1);

namespace OCA\QuickNotes\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version00850Date20260608170000 extends SimpleMigrationStep {

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

			if (!$table->hasColumn('archived_at')) {
				$table->addColumn('archived_at', 'datetime', [
					'notnull' => false,
				]);
			}

			if (!$table->hasColumn('deleted_at')) {
				$table->addColumn('deleted_at', 'datetime', [
					'notnull' => false,
				]);
			}
		}

		return $schema;
	}

}
