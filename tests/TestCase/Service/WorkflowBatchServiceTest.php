<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Service;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventManager;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Engine\StateMachineEngine;
use Workflow\Engine\TransitionResult;
use Workflow\Model\WorkflowTableInterface;
use Workflow\Service\WorkflowBatchService;

class WorkflowBatchServiceTest extends TestCase
{
    private WorkflowBatchService $batchService;

    private Definition $definition;

    private StateMachineEngine $engine;

    public function setUp(): void
    {
        parent::setUp();
        $this->batchService = new WorkflowBatchService();
        $this->definition = $this->createOrderDefinition();
        $this->engine = new StateMachineEngine(new EventManager());
    }

    public function testApplyToEntities(): void
    {
        $table = $this->createMockTable();

        $entities = [
            new Entity(['id' => '1', 'state' => 'pending']),
            new Entity(['id' => '2', 'state' => 'pending']),
            new Entity(['id' => '3', 'state' => 'pending']),
        ];

        $result = $this->batchService->applyToEntities($table, $entities, 'pay');

        $this->assertSame(3, $result->getTotal());
        $this->assertSame(3, $result->getSuccessCount());
        $this->assertTrue($result->isFullSuccess());

        // Verify entities were transitioned
        foreach ($entities as $entity) {
            $this->assertSame('paid', $entity->get('state'));
        }
    }

    public function testApplyToEntitiesWithFailures(): void
    {
        $table = $this->createMockTable();

        $entities = [
            new Entity(['id' => '1', 'state' => 'pending']),
            new Entity(['id' => '2', 'state' => 'paid']), // Already paid, can't pay again
            new Entity(['id' => '3', 'state' => 'pending']),
        ];

        $result = $this->batchService->applyToEntities($table, $entities, 'pay');

        $this->assertSame(3, $result->getTotal());
        $this->assertSame(2, $result->getSuccessCount());
        $this->assertSame(1, $result->getFailureCount());
        $this->assertFalse($result->isFullSuccess());
        $this->assertTrue($result->hasSuccesses());
        $this->assertTrue($result->hasFailures());
    }

    public function testApplyToEntitiesStopOnFailure(): void
    {
        $table = $this->createMockTable();

        $entities = [
            new Entity(['id' => '1', 'state' => 'pending']),
            new Entity(['id' => '2', 'state' => 'paid']), // Will fail
            new Entity(['id' => '3', 'state' => 'pending']), // Won't be processed
        ];

        $result = $this->batchService->applyToEntities(
            $table,
            $entities,
            'pay',
            [],
            stopOnFailure: true,
        );

        $this->assertSame(2, $result->getTotal()); // Only 2 processed
        $this->assertSame(1, $result->getSuccessCount());
        $this->assertSame(1, $result->getFailureCount());
    }

    public function testApplyToEntitiesWithContext(): void
    {
        $table = $this->createMockTable();

        $entities = [
            new Entity(['id' => '1', 'state' => 'pending']),
        ];

        $context = ['user_id' => 'admin-1', 'reason' => 'Bulk processing'];
        $result = $this->batchService->applyToEntities($table, $entities, 'pay', $context);

        $this->assertTrue($result->isFullSuccess());
    }

    public function testApplyToEntitiesSkipsNonEntities(): void
    {
        $table = $this->createMockTable();

        $entities = [
            new Entity(['id' => '1', 'state' => 'pending']),
            'not an entity',
            null,
            new Entity(['id' => '2', 'state' => 'pending']),
        ];

        $result = $this->batchService->applyToEntities($table, $entities, 'pay');

        $this->assertSame(2, $result->getTotal());
        $this->assertSame(2, $result->getSuccessCount());
    }

    public function testApplyToEntitiesWithoutBehaviorThrowsException(): void
    {
        $table = new Table();
        $table->setAlias('TestTable');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table "TestTable" must have WorkflowBehavior attached');

        $this->batchService->applyToEntities($table, [], 'transition');
    }

    private function createOrderDefinition(): Definition
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

    /**
     * Create a mock table that delegates to real workflow engine logic.
     *
     * Uses an anonymous class extending Table and implementing WorkflowTableInterface
     * to simulate the behavior methods added by WorkflowBehavior.
     *
     * @return \Cake\ORM\Table&\Workflow\Model\WorkflowTableInterface Table with workflow behavior methods
     */
    private function createMockTable(): Table
    {
        $definition = $this->definition;
        $engine = $this->engine;

        return new class ($definition, $engine) extends Table implements WorkflowTableInterface {
            private Definition $workflowDefinition;

            private StateMachineEngine $workflowEngine;

            public function __construct(Definition $definition, StateMachineEngine $engine)
            {
                parent::__construct();
                $this->workflowDefinition = $definition;
                $this->workflowEngine = $engine;
            }

            public function hasBehavior(string $name): bool
            {
                return $name === 'Workflow';
            }

            public function getWorkflowDefinition(): Definition
            {
                return $this->workflowDefinition;
            }

            public function applyTransition(EntityInterface $entity, string $transition, array $context = []): TransitionResult
            {
                return $this->workflowEngine->apply($this->workflowDefinition, $entity, $transition, $context);
            }

            public function canTransition(EntityInterface $entity, string $transition, array $context = []): bool
            {
                return $this->workflowEngine->can($this->workflowDefinition, $entity, $transition, $context);
            }

            public function getAvailableTransitions(EntityInterface $entity): array
            {
                return $this->workflowEngine->getAvailableTransitions($this->workflowDefinition, $entity);
            }

            public function getCurrentState(EntityInterface $entity): string
            {
                return $this->workflowEngine->getCurrentState($this->workflowDefinition, $entity);
            }
        };
    }
}
