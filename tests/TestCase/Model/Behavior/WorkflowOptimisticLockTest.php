<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Model\Behavior;

use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Cake\ORM\Table;
use TestApp\Model\SideEffectRecorder;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Loader\LoaderInterface;
use Workflow\Model\Behavior\WorkflowBehavior;
use Workflow\Service\WorkflowRegistry;
use Workflow\Test\TestCase\DatabaseTestCase;

/**
 * Optimistic (lock-free) concurrency: the behavior persists a state change with a
 * compare-and-set, so a writer acting on a stale state loses cleanly.
 */
class WorkflowOptimisticLockTest extends DatabaseTestCase
{
    private Table $orders;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();

        SideEffectRecorder::$count = 0;
        $registry = $this->createRegistry();
        $this->orders = new Table([
            'table' => 'orders',
            'alias' => 'Orders',
            'connection' => ConnectionManager::get('test'),
        ]);
        $this->orders->setPrimaryKey('id');
        $this->orders->addBehavior('Workflow', [
            'className' => WorkflowBehavior::class,
            'workflow' => 'order',
            'registry' => $registry,
            'useOptimisticLock' => true,
        ]);
    }

    private function behavior(): WorkflowBehavior
    {
        /** @var \Workflow\Model\Behavior\WorkflowBehavior $behavior */
        $behavior = $this->orders->getBehavior('Workflow');

        return $behavior;
    }

    public function testOptimisticTransitionPersists(): void
    {
        $order = $this->orders->newEntity(['state' => 'pending']);
        $this->orders->saveOrFail($order);

        $result = $this->behavior()->transition($order, 'pay');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('paid', $this->orders->get($order->get('id'))->get('state'));
    }

    public function testStaleWriterLosesWithConflict(): void
    {
        $seed = $this->orders->newEntity(['state' => 'pending']);
        $this->orders->saveOrFail($seed);
        $id = $seed->get('id');

        // Two independent reads, both still see "pending".
        $first = $this->orders->get($id);
        $stale = $this->orders->get($id);

        $winner = $this->behavior()->transition($first, 'pay');
        $this->assertTrue($winner->isSuccess());

        // The stale writer's compare-and-set matches no row (state is now "paid").
        $loser = $this->behavior()->transition($stale, 'pay');

        $this->assertTrue($loser->isLocked());
        $this->assertFalse($loser->isSuccess());
        $this->assertSame('pending', $stale->get('state')); // reverted in memory
        // The row advanced exactly once.
        $this->assertSame('paid', $this->orders->get($id)->get('state'));
        // Crucially, the command ran for the winner only - the lost claim executed
        // no side effects (claim happens before commands).
        $this->assertSame(1, SideEffectRecorder::$count);
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
                new Transition(
                    'pay',
                    ['pending'],
                    'paid',
                    happy: true,
                    commands: [SideEffectRecorder::class . '::record'],
                ),
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
