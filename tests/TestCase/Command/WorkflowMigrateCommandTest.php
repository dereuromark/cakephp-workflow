<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\StateTimeout;
use Workflow\Engine\Definition\Transition;
use Workflow\Loader\LoaderInterface;
use Workflow\Service\WorkflowRegistry;
use Workflow\Test\TestCase\DatabaseTestCase;

class WorkflowMigrateCommandTest extends DatabaseTestCase
{
    use ConsoleIntegrationTestTrait;

    private Definition $definition;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();
        $this->definition = $this->createDefinition();
        Configure::write(
            'Workflow.registry',
            new WorkflowRegistry($this->loaderFor($this->definition), new EventManager()),
        );
    }

    public function tearDown(): void
    {
        Configure::delete('Workflow.registry');
    }

    public function testMigrateMapsOrphanedRecordsAndLogsTransition(): void
    {
        $this->insertOrder('legacy');

        $this->exec('workflow migrate order --map legacy:pending');

        $this->assertExitSuccess();
        $states = $this->orderStates();
        $this->assertContains('pending', $states);
        $this->assertNotContains('legacy', $states);

        $logRow = ConnectionManager::get('test')
            ->execute("SELECT COUNT(*) AS c, MAX(workflow_version) AS v FROM workflow_transitions WHERE from_state = 'legacy' AND to_state = 'pending'")
            ->fetch('assoc');
        $this->assertSame(1, (int)$logRow['c']);
        // The audit column holds the human workflow version, like normal transitions.
        $this->assertSame((string)$this->definition->getVersion(), $logRow['v']);
    }

    public function testMigrateReportsNoOrphans(): void
    {
        $this->insertOrder('pending');

        $this->exec('workflow migrate order');

        $this->assertExitSuccess();
        $this->assertOutputContains('No orphaned records');
    }

    public function testMigrateRefusesWhenOrphanedStateUnmapped(): void
    {
        $this->insertOrder('legacy');

        $this->exec('workflow migrate order');

        $this->assertExitError();
        $this->assertErrorContains('legacy');
        $this->assertContains('legacy', $this->orderStates());
    }

    public function testMigrateDryRunMakesNoChanges(): void
    {
        $this->insertOrder('legacy');

        $this->exec('workflow migrate order --map legacy:pending --dry-run');

        $this->assertExitSuccess();
        $this->assertContains('legacy', $this->orderStates());
    }

    public function testMigrateSchedulesTimeoutsForTargetState(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true, timeouts: [new StateTimeout('PT1H', 'pay')]),
                new State('paid'),
                new State('completed', final: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
                new Transition('complete', ['paid'], 'completed'),
            ],
        );
        Configure::write(
            'Workflow.registry',
            new WorkflowRegistry($this->loaderFor($definition), new EventManager()),
        );

        ConnectionManager::get('test')->execute(
            'INSERT INTO orders (id, state) VALUES (?, ?)',
            [1, 'legacy'],
        );

        $this->exec('workflow migrate order --map legacy:pending');

        $this->assertExitSuccess();
        $timeoutCount = (int)ConnectionManager::get('test')
            ->execute("SELECT COUNT(*) AS c FROM workflow_timeouts WHERE current_state = 'pending' AND foreign_key = '1'")
            ->fetch('assoc')['c'];
        $this->assertSame(1, $timeoutCount);
    }

    public function testMigrateAbortsAndRollsBackWhenAuditLogFails(): void
    {
        $this->insertOrder('legacy');
        // Force audit-log writes to fail so the migration must roll back.
        ConnectionManager::get('test')->execute('DROP TABLE workflow_transitions');

        try {
            $this->exec('workflow migrate order --map legacy:pending');

            $this->assertExitError();
            $states = $this->orderStates();
            $this->assertContains('legacy', $states);
            $this->assertNotContains('pending', $states);
        } finally {
            $this->recreateTransitionsTable();
        }
    }

    private function recreateTransitionsTable(): void
    {
        // Must mirror DatabaseTestCase::createSchema()'s workflow_transitions table exactly:
        // this recreates the shared in-memory table after the rollback test drops it, so a
        // missing column would corrupt the schema for every later test in the suite.
        ConnectionManager::get('test')->execute('
            CREATE TABLE workflow_transitions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workflow_name VARCHAR(64) NOT NULL,
                model VARCHAR(128) NOT NULL,
                foreign_key BIGINT NOT NULL,
                transition_name VARCHAR(64) NOT NULL,
                from_state VARCHAR(64) NOT NULL,
                to_state VARCHAR(64) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'success\',
                user_id VARCHAR(36),
                reason TEXT,
                context TEXT,
                idempotency_key VARCHAR(128),
                workflow_version VARCHAR(16),
                created DATETIME NOT NULL
            )
        ');
    }

    private function createDefinition(): Definition
    {
        return new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
                new State('shipped'),
                new State('completed', final: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
                new Transition('ship', ['paid'], 'shipped'),
                new Transition('complete', ['shipped'], 'completed'),
            ],
        );
    }

    private function loaderFor(Definition $definition): LoaderInterface
    {
        return new class ($definition) implements LoaderInterface {
            public function __construct(private readonly Definition $definition)
            {
            }

            public function supports(string $workflowName): bool
            {
                return $workflowName === $this->definition->getName();
            }

            public function load(string $workflowName): Definition
            {
                return $this->definition;
            }

            public function getWorkflowNames(): array
            {
                return [$this->definition->getName()];
            }
        };
    }

    private function insertOrder(string $state): void
    {
        ConnectionManager::get('test')->execute(
            'INSERT INTO orders (state) VALUES (?)',
            [$state],
        );
    }

    /**
     * @return array<string>
     */
    private function orderStates(): array
    {
        $rows = ConnectionManager::get('test')
            ->execute('SELECT state FROM orders')
            ->fetchAll('assoc');

        return array_map(fn ($row) => $row['state'], $rows);
    }
}
