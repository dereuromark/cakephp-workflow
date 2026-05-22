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

class WorkflowApplyCommandTest extends DatabaseTestCase
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

    public function testApplyTransition(): void
    {
        $order = $this->orders->newEntity(['state' => 'pending']);
        $this->orders->saveOrFail($order);

        $this->exec(sprintf('workflow apply order %s pay --reason "manual"', $order->get('id')));

        $this->assertExitSuccess();
        $this->assertOutputContains('pending -> paid');
        $this->assertSame('paid', $this->orders->get($order->get('id'))->get('state'));
    }

    public function testApplyDryRunDoesNotChangeState(): void
    {
        $order = $this->orders->newEntity(['state' => 'pending']);
        $this->orders->saveOrFail($order);

        $this->exec(sprintf('workflow apply order %s pay --dry-run', $order->get('id')));

        $this->assertExitSuccess();
        $this->assertOutputContains('allowed');
        $this->assertSame('pending', $this->orders->get($order->get('id'))->get('state'));
    }

    public function testApplyMissingRecord(): void
    {
        $this->exec('workflow apply order 999999 pay');

        $this->assertExitError();
        $this->assertErrorContains('not found');
    }

    public function testApplyRejectsTableWithDifferentWorkflowBehavior(): void
    {
        $order = $this->orders->newEntity(['state' => 'pending']);
        $this->orders->saveOrFail($order);

        // Same table, but its behavior is configured for a different workflow.
        $mismatch = new Table([
            'table' => 'orders',
            'alias' => 'Orders',
            'connection' => ConnectionManager::get('test'),
        ]);
        $mismatch->addBehavior('Workflow', [
            'className' => WorkflowBehavior::class,
            'workflow' => 'other',
            'useLocking' => false,
            'validateOnSave' => false,
        ]);
        TableRegistry::getTableLocator()->set('Orders', $mismatch);

        $this->exec(sprintf('workflow apply order %s pay', $order->get('id')));

        $this->assertExitError();
        $this->assertErrorContains("not 'order'");
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
