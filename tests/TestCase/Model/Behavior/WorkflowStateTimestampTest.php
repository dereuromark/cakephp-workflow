<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Model\Behavior;

use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Loader\LoaderInterface;
use Workflow\Model\Behavior\WorkflowBehavior;
use Workflow\Service\WorkflowRegistry;
use Workflow\Test\TestCase\DatabaseTestCase;

/**
 * Time-in-state tracking: the behavior stamps a configurable timestamp column on
 * every real state change.
 */
class WorkflowStateTimestampTest extends DatabaseTestCase
{
    private WorkflowRegistry $registry;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();
        $this->registry = $this->createRegistry();
    }

    private function ordersTable(array $behaviorConfig = []): Table
    {
        $table = new Table([
            'table' => 'orders',
            'alias' => 'Orders',
            'connection' => ConnectionManager::get('test'),
        ]);
        $table->setPrimaryKey('id');
        $table->addBehavior('Workflow', $behaviorConfig + [
            'className' => WorkflowBehavior::class,
            'workflow' => 'order',
            'registry' => $this->registry,
            'useLocking' => false,
        ]);

        return $table;
    }

    public function testStampsStateChangedAtOnTransition(): void
    {
        $table = $this->ordersTable();
        $order = $table->newEntity(['state' => 'pending']);
        $table->saveOrFail($order);

        $table->getBehavior('Workflow')->transition($order, 'pay');

        $this->assertInstanceOf(DateTime::class, $order->get('state_changed_at'));
        // Persisted to the database, not just in memory.
        $this->assertNotNull($table->get($order->get('id'))->get('state_changed_at'));
    }

    public function testDoesNotStampWhenDisabled(): void
    {
        $table = $this->ordersTable(['stateTimestampField' => null]);
        $order = $table->newEntity(['state' => 'pending']);
        $table->saveOrFail($order);

        $table->getBehavior('Workflow')->transition($order, 'pay');

        $this->assertSame('paid', $order->get('state'));
        $this->assertNull($order->get('state_changed_at'));
    }

    public function testDoesNotStampOnSelfLoop(): void
    {
        $table = $this->ordersTable();
        $order = $table->newEntity(['state' => 'pending']);
        $table->saveOrFail($order);

        // Self-transition (pending -> pending) is not a real state change.
        $table->getBehavior('Workflow')->transition($order, 'touch');

        $this->assertSame('pending', $order->get('state'));
        $this->assertNull($order->get('state_changed_at'));
    }

    public function testNoErrorWhenColumnAbsent(): void
    {
        $table = $this->ordersTable(['stateTimestampField' => 'missing_column']);
        $order = $table->newEntity(['state' => 'pending']);
        $table->saveOrFail($order);

        $result = $table->getBehavior('Workflow')->transition($order, 'pay');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('paid', $order->get('state'));
    }

    private function createRegistry(): WorkflowRegistry
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid', final: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', happy: true),
                new Transition('touch', ['pending'], 'pending'),
            ],
        );

        $loader = new class ($definition) implements LoaderInterface {
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

        return new WorkflowRegistry($loader, EventManager::instance());
    }
}
