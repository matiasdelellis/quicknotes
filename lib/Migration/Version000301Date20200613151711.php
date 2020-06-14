<?php

declare(strict_types=1);

namespace OCA\QuickNotes\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000301Date20200613151711 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('quicknotes_attach')) {
			$table = $schema->createTable('quicknotes_attach');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 8,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 200,
				'default' => '',
			]);
			$table->addColumn('note_id', 'bigint', [
				'notnull' => true,
				'length' => 8,
				'unsigned' => true,
			]);
			$table->addColumn('file_id', 'bigint', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->addColumn('created_at', 'bigint', [
				'notnull' => false,
				'length' => 8,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'attach_user_id_index');
			$table->addIndex(['note_id'], 'attach_note_id_index');
			$table->addIndex(['file_id'], 'attach_file_id_index');
		}

		return $schema;
	}

}
