<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Model\Behavior;

use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Cake\ORM\Table;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\StateTimeout;
use Workflow\Engine\Definition\Transition;
use Workflow\Loader\LoaderInterface;
use Workflow\Model\Behavior\WorkflowBehavior;
use Workflow\Service\WorkflowRegistry;
use Workflow\Test\TestCase\DatabaseTestCase;

#[AllowMockObjectsWithoutExpectations]
class WorkflowBehaviorPersistenceTest extends DatabaseTestCase
{
    private Table $ordersTable;

    private WorkflowBehavior $workflowBehavior;

    private WorkflowRegistry $registry;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();
        $this->createOrdersTable();
        $this->truncateOrders();
        $this->registry = $this->createMockRegistry();

        $this->ordersTable = new Table([
            'table' => 'orders',
            'alias' => 'Orders',
            'connection' => ConnectionManager::get('test'),
        ]);
        $this->ordersTable->setPrimaryKey('id');

        $this->ordersTable->addBehavior('Workflow', [
            'className' => WorkflowBehavior::class,
            'workflow' => 'order',
            'registry' => $this->registry,
            'useLocking' => false,
        ]);
        /** @var \Workflow\Model\Behavior\WorkflowBehavior $workflowBehavior */
        $workflowBehavior = $this->ordersTable->behaviors()->get('Workflow');
        $this->workflowBehavior = $workflowBehavior;
    }

    private function createOrdersTable(): void
    {
        $connection = ConnectionManager::get('test');
        $schemaCollection = $connection->getSchemaCollection();
        $existingTables = $schemaCollection->listTables();
        if (in_array('orders', $existingTables, true)) {
            return;
        }

        $connection->execute('
            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                state VARCHAR(64) NOT NULL,
                created DATETIME,
                modified DATETIME
            )
        ');
    }

    private function truncateOrders(): void
    {
        ConnectionManager::get('test')->execute('DELETE FROM orders');
    }

    private function createMockRegistry(): WorkflowRegistry
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid', timeouts: [new StateTimeout('PT1H', 'ship')]),
                new State('shipped'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
                new Transition('ship', ['paid'], 'shipped'),
            ],
        );

        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('supports')->willReturn(true);
        $loader->method('load')->willReturn($definition);

        return new WorkflowRegistry($loader, EventManager::instance());
    }

    public function testTransitionSchedulesTimeoutsForPersistedEntity(): void
    {
        $order = $this->ordersTable->newEntity(['state' => 'pending']);
        $this->ordersTable->saveOrFail($order);

        $result = $this->workflowBehavior->transition($order, 'pay', [], [
            'log' => false,
            'timeouts' => true,
        ]);

        $this->assertTrue($result->isSuccess());

        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $timeouts = $timeoutsTable->find()->toArray();

        $this->assertCount(1, $timeouts);
        $this->assertSame('paid', $timeouts[0]->current_state);
        $this->assertSame('ship', $timeouts[0]->transition_name);
        $this->assertFalse($timeouts[0]->processed);
    }

    public function testTransitionMarksPreviousTimeoutsProcessedWhenNewStateHasNone(): void
    {
        $order = $this->ordersTable->newEntity(['state' => 'pending']);
        $this->ordersTable->saveOrFail($order);

        $this->workflowBehavior->transition($order, 'pay', [], [
            'log' => false,
            'timeouts' => true,
        ]);

        $result = $this->workflowBehavior->transition($order, 'ship', [], [
            'log' => false,
            'timeouts' => true,
        ]);

        $this->assertTrue($result->isSuccess());

        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $timeouts = $timeoutsTable->find()->orderByAsc('id')->toArray();

        $this->assertCount(1, $timeouts);
        $this->assertTrue($timeouts[0]->processed);
        $this->assertSame('ship', $timeouts[0]->transition_name);
    }
}
