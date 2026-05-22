<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Model\Behavior;

use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Cake\ORM\Table;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Loader\LoaderInterface;
use Workflow\Model\Behavior\WorkflowBehavior;
use Workflow\Service\WorkflowRegistry;
use Workflow\Test\TestCase\DatabaseTestCase;

/**
 * Regression coverage for stale-state / double-execution: while holding the lock
 * the behavior must re-read the persisted state, so a caller working from a stale
 * in-memory entity cannot re-apply a transition another caller already applied.
 */
#[AllowMockObjectsWithoutExpectations]
class WorkflowBehaviorConcurrencyTest extends DatabaseTestCase
{
    private Table $ordersTable;

    private WorkflowBehavior $workflowBehavior;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();
        $this->createOrdersTable();
        ConnectionManager::get('test')->execute('DELETE FROM orders');

        $this->ordersTable = new Table([
            'table' => 'orders',
            'alias' => 'Orders',
            'connection' => ConnectionManager::get('test'),
        ]);
        $this->ordersTable->setPrimaryKey('id');
        $this->ordersTable->addBehavior('Workflow', [
            'className' => WorkflowBehavior::class,
            'workflow' => 'order',
            'registry' => $this->createMockRegistry(),
            'useLocking' => true,
        ]);
        /** @var \Workflow\Model\Behavior\WorkflowBehavior $behavior */
        $behavior = $this->ordersTable->behaviors()->get('Workflow');
        $this->workflowBehavior = $behavior;
    }

    public function testStaleEntityTransitionIsBlockedAfterConcurrentUpdate(): void
    {
        $orderA = $this->ordersTable->newEntity(['state' => 'pending']);
        $this->ordersTable->saveOrFail($orderA);

        // A second handle that loaded the row while it was still 'pending',
        // simulating a second concurrent request/process.
        $orderB = $this->ordersTable->get($orderA->get('id'));

        // First caller applies the transition; DB is now 'paid'.
        $resultA = $this->workflowBehavior->transition($orderA, 'pay', [], ['log' => false, 'lock' => true]);
        $this->assertTrue($resultA->isSuccess());

        // Stale caller tries the same transition from its in-memory 'pending'.
        // The under-lock state refresh sees 'paid', so 'pay' is no longer allowed.
        $resultB = $this->workflowBehavior->transition($orderB, 'pay', [], ['log' => false, 'lock' => true]);

        $this->assertFalse($resultB->isSuccess());
        $this->assertTrue($resultB->isBlocked());
        $this->assertSame('paid', $resultB->getFromState());

        // And the row is still 'paid' — the transition did not run twice.
        $reloaded = $this->ordersTable->get($orderA->get('id'));
        $this->assertSame('paid', $reloaded->get('state'));
    }

    private function createOrdersTable(): void
    {
        $connection = ConnectionManager::get('test');
        if (in_array('orders', $connection->getSchemaCollection()->listTables(), true)) {
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

    private function createMockRegistry(): WorkflowRegistry
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
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
}
