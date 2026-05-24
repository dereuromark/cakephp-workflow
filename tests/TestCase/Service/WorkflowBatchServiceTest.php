<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Service;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Cake\ORM\Table;
use InvalidArgumentException;
use TestApp\Model\Table\BatchOrdersTable;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Loader\LoaderInterface;
use Workflow\Model\Behavior\WorkflowBehavior;
use Workflow\Service\WorkflowBatchService;
use Workflow\Service\WorkflowRegistry;
use Workflow\Test\TestCase\DatabaseTestCase;

class WorkflowBatchServiceTest extends DatabaseTestCase
{
    private WorkflowBatchService $batchService;

    private Table $orders;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();

        $this->batchService = new WorkflowBatchService();

        $registry = $this->createRegistry();
        Configure::write('Workflow.registry', $registry);

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
            'useLocking' => false,
            'validateOnSave' => false,
        ]);
    }

    public function tearDown(): void
    {
        Configure::delete('Workflow.registry');
        parent::tearDown();
    }

    public function testApplyToStatePersistsAllMatching(): void
    {
        $ids = $this->seed(['pending', 'pending', 'pending']);

        $result = $this->batchService->applyToState($this->orders, 'pending', 'pay');

        $this->assertSame(3, $result->getTotal());
        $this->assertSame(3, $result->getSuccessCount());
        // Persisted, not just in memory.
        foreach ($ids as $id) {
            $this->assertSame('paid', $this->orders->get($id)->get('state'));
        }
    }

    public function testApplyToStateRespectsLimit(): void
    {
        $this->seed(['pending', 'pending', 'pending']);

        $result = $this->batchService->applyToState($this->orders, 'pending', 'pay', [], 2);

        $this->assertSame(2, $result->getTotal());
        $this->assertSame(2, $this->orders->find()->where(['state' => 'paid'])->count());
        $this->assertSame(1, $this->orders->find()->where(['state' => 'pending'])->count());
    }

    public function testApplyToEntitiesWithFailures(): void
    {
        $entities = [
            $this->orders->get($this->seedOne('pending')),
            $this->orders->get($this->seedOne('paid')), // can't pay again -> blocked
            $this->orders->get($this->seedOne('pending')),
        ];

        $result = $this->batchService->applyToEntities($this->orders, $entities, 'pay');

        $this->assertSame(3, $result->getTotal());
        $this->assertSame(2, $result->getSuccessCount());
        $this->assertSame(1, $result->getFailureCount());
        $this->assertTrue($result->hasSuccesses());
        $this->assertTrue($result->hasFailures());
    }

    public function testApplyToEntitiesStopOnFailure(): void
    {
        $entities = [
            $this->orders->get($this->seedOne('pending')),
            $this->orders->get($this->seedOne('paid')), // fails
            $this->orders->get($this->seedOne('pending')), // not processed
        ];

        $result = $this->batchService->applyToEntities($this->orders, $entities, 'pay', [], stopOnFailure: true);

        $this->assertSame(2, $result->getTotal());
        $this->assertSame(1, $result->getSuccessCount());
        $this->assertSame(1, $result->getFailureCount());
    }

    public function testApplyToEntitiesSkipsNonEntities(): void
    {
        $entities = [
            $this->orders->get($this->seedOne('pending')),
            'not an entity',
            null,
            $this->orders->get($this->seedOne('pending')),
        ];

        $result = $this->batchService->applyToEntities($this->orders, $entities, 'pay');

        $this->assertSame(2, $result->getTotal());
        $this->assertSame(2, $result->getSuccessCount());
    }

    public function testApplyToFinderRunsTheFinder(): void
    {
        $this->seed(['pending', 'pending']);

        $result = $this->batchService->applyToFinder($this->orders, 'all', 'pay');

        $this->assertSame(2, $result->getSuccessCount());
        $this->assertSame(2, $this->orders->find()->where(['state' => 'paid'])->count());
    }

    public function testApplyToFinderPassesNamedOptions(): void
    {
        $table = new BatchOrdersTable(['connection' => ConnectionManager::get('test')]);
        $table->saveOrFail($table->newEntity(['state' => 'pending']));
        $table->saveOrFail($table->newEntity(['state' => 'pending']));
        $table->saveOrFail($table->newEntity(['state' => 'paid']));

        // The 'state' option must reach the custom finder (required arg), so only
        // the two pending rows are selected and transitioned.
        $result = $this->batchService->applyToFinder($table, 'withState', 'pay', ['state' => 'pending']);

        $this->assertSame(2, $result->getTotal());
        $this->assertSame(2, $result->getSuccessCount());
        $this->assertSame(3, $table->find()->where(['state' => 'paid'])->count());
    }

    public function testWithoutBehaviorThrows(): void
    {
        $table = new Table();
        $table->setAlias('TestTable');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table "TestTable" must have WorkflowBehavior attached');

        $this->batchService->applyToEntities($table, [], 'pay');
    }

    /**
     * @param array<string> $states
     *
     * @return array<int>
     */
    private function seed(array $states): array
    {
        $ids = [];
        foreach ($states as $state) {
            $ids[] = $this->seedOne($state);
        }

        return $ids;
    }

    private function seedOne(string $state): int
    {
        $order = $this->orders->newEntity(['state' => $state]);
        $this->orders->saveOrFail($order);

        return (int)$order->get('id');
    }

    private function createRegistry(): WorkflowRegistry
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
                new State('shipped', final: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
                new Transition('ship', ['paid'], 'shipped'),
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
