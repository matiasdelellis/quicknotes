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
class Version000204Date20200530211356 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('quicknotes_notes')) {
			$table = $schema->createTable('quicknotes_notes');
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
			$table->addColumn('title', 'string', [
				'notnull' => true,
				'length' => 200,
				'default' => '',
			]);
			$table->addColumn('content', 'text', [
				'notnull' => true,
				'default' => '',
			]);
			$table->addColumn('timestamp', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('color_id', 'bigint', [
				'notnull' => true,
				'length' => 8,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('quicknotes_colors')) {
			$table = $schema->createTable('quicknotes_colors');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 8,
				'unsigned' => true,
			]);
			$table->addColumn('color', 'string', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('quicknotes_tags')) {
			$table = $schema->createTable('quicknotes_tags');
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
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 200,
				'default' => '',
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('quicknotes_note_tags')) {
			$table = $schema->createTable('quicknotes_note_tags');
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
			$table->addColumn('tag_id', 'bigint', [
				'notnull' => true,
				'length' => 8,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('quicknotes_tasks')) {
			$table = $schema->createTable('quicknotes_tasks');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 8,
				'unsigned' => true,
			]);
			$table->addColumn('note_id', 'bigint', [
				'notnull' => true,
				'length' => 8,
				'unsigned' => true,
			]);
			$table->addColumn('description', 'string', [
				'notnull' => true,
				'length' => 200,
				'default' => '',
			]);
			$table->addColumn('done', 'boolean', [
				'notnull' => true,
				'default' => false,
			]);
			$table->addColumn('ordering', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('quicknotes_shares')) {
			$table = $schema->createTable('quicknotes_shares');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 8,
				'unsigned' => true,
			]);
			$table->addColumn('note_id', 'bigint', [
				'notnull' => true,
				'length' => 8,
				'unsigned' => true,
			]);
			$table->addColumn('shared_user', 'string', [
				'notnull' => false,
				'length' => 200,
			]);
			$table->addColumn('shared_group', 'string', [
				'notnull' => false,
				'length' => 200,
			]);
			$table->setPrimaryKey(['id']);
		}
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
