<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Loader\LoaderInterface;
use Workflow\Model\Behavior\WorkflowBehavior;
use Workflow\Service\WorkflowRegistry;
use Workflow\Test\TestCase\DatabaseTestCase;

class WorkflowBatchCommandTest extends DatabaseTestCase
{
    use ConsoleIntegrationTestTrait;

    private Table $orders;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();

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
        ]);
        TableRegistry::getTableLocator()->set('Orders', $this->orders);
    }

    public function tearDown(): void
    {
        Configure::delete('Workflow.registry');
        TableRegistry::getTableLocator()->remove('Orders');

        parent::tearDown();
    }

    private function seedPending(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->orders->saveOrFail($this->orders->newEntity(['state' => 'pending']));
        }
    }

    public function testBatchAdvancesAllMatchingRecords(): void
    {
        $this->seedPending(3);

        $this->exec('workflow batch order pay --state pending');

        $this->assertExitSuccess();
        $this->assertOutputContains('Processed 3 record(s): 3 succeeded, 0 failed.');
        $this->assertSame(3, $this->orders->find()->where(['state' => 'paid'])->count());
        $this->assertSame(0, $this->orders->find()->where(['state' => 'pending'])->count());
    }

    public function testBatchDryRunCountsWithoutChanging(): void
    {
        $this->seedPending(2);

        $this->exec('workflow batch order pay --state pending --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('Dry run: 2 record(s)');
        $this->assertSame(2, $this->orders->find()->where(['state' => 'pending'])->count());
    }

    public function testBatchRespectsLimit(): void
    {
        $this->seedPending(3);

        $this->exec('workflow batch order pay --state pending --limit 2');

        $this->assertExitSuccess();
        $this->assertOutputContains('Processed 2 record(s)');
        $this->assertSame(2, $this->orders->find()->where(['state' => 'paid'])->count());
        $this->assertSame(1, $this->orders->find()->where(['state' => 'pending'])->count());
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
            ],
        );

        $loader = new class ($definition) implements LoaderInterface {
            public function __construct(private Definition $definition)
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
