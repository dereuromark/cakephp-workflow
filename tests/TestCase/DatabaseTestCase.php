<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase;

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
        $connection = ConnectionManager::get('test');
        $schemaCollection = $connection->getSchemaCollection();
        $existingTables = $schemaCollection->listTables();

        // Create workflow_locks table
        if (!in_array('workflow_locks', $existingTables, true)) {
            $connection->execute('
                CREATE TABLE workflow_locks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    workflow_name VARCHAR(64) NOT NULL,
                    entity_table VARCHAR(128) NOT NULL,
                    entity_id VARCHAR(36) NOT NULL,
                    locked_by VARCHAR(128),
                    expires_at DATETIME NOT NULL,
                    created DATETIME NOT NULL
                )
            ');
        }

        // Create workflow_transitions table
        if (!in_array('workflow_transitions', $existingTables, true)) {
            $connection->execute('
                CREATE TABLE workflow_transitions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    workflow_name VARCHAR(64) NOT NULL,
                    entity_table VARCHAR(128) NOT NULL,
                    entity_id VARCHAR(36) NOT NULL,
                    transition_name VARCHAR(64) NOT NULL,
                    from_state VARCHAR(64) NOT NULL,
                    to_state VARCHAR(64) NOT NULL,
                    status VARCHAR(16) NOT NULL DEFAULT \'success\',
                    user_id VARCHAR(36),
                    reason TEXT,
                    context TEXT,
                    idempotency_key VARCHAR(255),
                    workflow_version VARCHAR(16),
                    created DATETIME NOT NULL
                )
            ');
        }

        // Create workflow_timeouts table
        if (!in_array('workflow_timeouts', $existingTables, true)) {
            $connection->execute('
                CREATE TABLE workflow_timeouts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    workflow_name VARCHAR(64) NOT NULL,
                    entity_table VARCHAR(128) NOT NULL,
                    entity_id VARCHAR(36) NOT NULL,
                    current_state VARCHAR(64) NOT NULL,
                    transition_name VARCHAR(64) NOT NULL,
                    due_at DATETIME NOT NULL,
                    processed BOOLEAN DEFAULT 0 NOT NULL,
                    created DATETIME NOT NULL
                )
            ');
        }

        if (!in_array('orders', $existingTables, true)) {
            $connection->execute('
                CREATE TABLE orders (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    state VARCHAR(64),
                    total DECIMAL(10,2),
                    payment_captured BOOLEAN DEFAULT 0 NOT NULL
                )
            ');
        }

        if (!in_array('payments', $existingTables, true)) {
            $connection->execute('
                CREATE TABLE payments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    status VARCHAR(64)
                )
            ');
        }
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
