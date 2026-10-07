<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase;

use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\TestCase;

/**
 * Base test case for database-dependent tests.
 */
abstract class DatabaseTestCase extends TestCase
{
    use LocatorAwareTrait;

    /**
     * @var bool
     */
    protected static bool $schemaCreated = false;

    public function setUp(): void
    {
        parent::setUp();

        if (!static::$schemaCreated) {
            $this->createSchema();
            static::$schemaCreated = true;
        }
    }

    protected function createSchema(): void
    {
        $existingTables = ConnectionManager::get('test')->getSchemaCollection()->listTables();

        if (!in_array('workflow_locks', $existingTables, true)) {
            $this->createTable('workflow_locks', [
                'workflow_name' => ['type' => 'string', 'length' => 64, 'null' => false],
                'model' => ['type' => 'string', 'length' => 128, 'null' => false],
                'foreign_key' => ['type' => 'integer', 'null' => false],
                'locked_by' => ['type' => 'string', 'length' => 128, 'null' => true],
                'expires_at' => ['type' => 'datetime', 'null' => false],
                'created' => ['type' => 'datetime', 'null' => false],
            ]);
        }

        if (!in_array('workflow_transitions', $existingTables, true)) {
            $this->createTransitionsTable();
        }

        if (!in_array('workflow_timeouts', $existingTables, true)) {
            $this->createTable('workflow_timeouts', [
                'workflow_name' => ['type' => 'string', 'length' => 64, 'null' => false],
                'model' => ['type' => 'string', 'length' => 128, 'null' => false],
                'foreign_key' => ['type' => 'integer', 'null' => false],
                'current_state' => ['type' => 'string', 'length' => 64, 'null' => false],
                'transition_name' => ['type' => 'string', 'length' => 64, 'null' => false],
                'due_at' => ['type' => 'datetime', 'null' => false],
                'processed' => ['type' => 'boolean', 'null' => false, 'default' => false],
                'created' => ['type' => 'datetime', 'null' => false],
            ]);
        }

        if (!in_array('orders', $existingTables, true)) {
            $this->createTable('orders', [
                'state' => ['type' => 'string', 'length' => 64, 'null' => true],
                'total' => ['type' => 'decimal', 'length' => 10, 'precision' => 2, 'null' => true],
                'payment_captured' => ['type' => 'boolean', 'null' => false, 'default' => false],
                'state_changed_at' => ['type' => 'datetime', 'null' => true],
            ]);
        }

        if (!in_array('payments', $existingTables, true)) {
            $this->createTable('payments', [
                'status' => ['type' => 'string', 'length' => 64, 'null' => true],
            ]);
        }
    }

    /**
     * Creates a table with an auto-increment `id` on the test connection.
     * Built from a schema definition so the SQL fits whichever database the
     * suite runs on.
     *
     * @param string $name Table name
     * @param array<string, array<string, mixed>> $columns Column definitions without the `id`
     *
     * @return void
     */
    protected function createTable(string $name, array $columns): void
    {
        $connection = ConnectionManager::get('test');
        $schema = new TableSchema($name, ['id' => ['type' => 'integer', 'autoIncrement' => true]] + $columns);
        $schema->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);

        foreach ($schema->createSql($connection) as $sql) {
            $connection->execute($sql);
        }
    }

    /**
     * @return void
     */
    protected function createTransitionsTable(): void
    {
        $this->createTable('workflow_transitions', [
            'workflow_name' => ['type' => 'string', 'length' => 64, 'null' => false],
            'model' => ['type' => 'string', 'length' => 128, 'null' => false],
            'foreign_key' => ['type' => 'biginteger', 'null' => false],
            'transition_name' => ['type' => 'string', 'length' => 64, 'null' => false],
            'from_state' => ['type' => 'string', 'length' => 64, 'null' => false],
            'to_state' => ['type' => 'string', 'length' => 64, 'null' => false],
            'status' => ['type' => 'string', 'length' => 16, 'null' => false, 'default' => 'success'],
            'user_id' => ['type' => 'string', 'length' => 36, 'null' => true],
            'reason' => ['type' => 'text', 'null' => true],
            'context' => ['type' => 'text', 'null' => true],
            'idempotency_key' => ['type' => 'string', 'length' => 128, 'null' => true],
            'workflow_version' => ['type' => 'string', 'length' => 16, 'null' => true],
            'created' => ['type' => 'datetime', 'null' => false],
        ]);
    }

    /**
     * Truncate all workflow tables.
     */
    protected function truncateTables(): void
    {
        $connection = ConnectionManager::get('test');
        $connection->execute('DELETE FROM workflow_locks');
        $connection->execute('DELETE FROM workflow_transitions');
        $connection->execute('DELETE FROM workflow_timeouts');
        $connection->execute('DELETE FROM orders');
        $connection->execute('DELETE FROM payments');
    }
}
