<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Loader\LoaderInterface;
use Workflow\Model\Behavior\WorkflowBehavior;
use Workflow\Service\WorkflowRegistry;
use Workflow\Test\TestCase\DatabaseTestCase;

/**
 * Regression: the timeouts command must be able to apply a transition on an entity
 * table that uses the Workflow behavior. Previously it called the engine directly and
 * then saveOrFail(), which the behavior's beforeSave rejected ("Direct state changes
 * are not allowed"), leaving the entity stuck.
 */
class WorkflowTimeoutsCommandTest extends DatabaseTestCase
{
    use ConsoleIntegrationTestTrait;

    private Table $ordersTable;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();

        $registry = $this->createRegistry();
        Configure::write('Workflow.registry', $registry);

        // The orders table is created and truncated by DatabaseTestCase.
        $this->ordersTable = new Table([
            'table' => 'orders',
            'alias' => 'Orders',
            'connection' => ConnectionManager::get('test'),
        ]);
        $this->ordersTable->setPrimaryKey('id');
        $this->ordersTable->addBehavior('Workflow', [
            'className' => WorkflowBehavior::class,
            'workflow' => 'order',
            'registry' => $registry,
            'useLocking' => false,
        ]);

        // The command resolves the entity table by name; hand it the instance that
        // has the behavior attached.
        TableRegistry::getTableLocator()->set('Orders', $this->ordersTable);
    }

    public function tearDown(): void
    {
        Configure::delete('Workflow.registry');
        TableRegistry::getTableLocator()->remove('Orders');
    }

    public function testProcessesDueTimeoutOnTableWithBehavior(): void
    {
        $order = $this->ordersTable->newEntity(['state' => 'pending']);
        $this->ordersTable->saveOrFail($order);

        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $timeoutsTable->saveOrFail($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'model' => 'Orders',
            'foreign_key' => (string)$order->get('id'),
            'current_state' => 'pending',
            'transition_name' => 'pay',
            'due_at' => DateTime::now('UTC')->subSeconds(60),
            'processed' => false,
        ]));

        $this->exec('workflow timeouts');

        $this->assertExitSuccess();
        $this->assertOutputContains('Processed: 1, Errors: 0');

        $reloaded = $this->ordersTable->get($order->get('id'));
        $this->assertSame('paid', $reloaded->get('state'));

        $processedTimeout = $timeoutsTable->get($timeoutsTable->find()->firstOrFail()->get('id'));
        $this->assertTrue((bool)$processedTimeout->get('processed'));
    }

    public function testOrphanedTimeoutForDeletedEntityIsMarkedProcessed(): void
    {
        // A timeout pointing at an entity that no longer exists must be skipped and
        // marked processed, not error on every run.
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $timeoutsTable->saveOrFail($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'model' => 'Orders',
            'foreign_key' => '999999',
            'current_state' => 'pending',
            'transition_name' => 'pay',
            'due_at' => DateTime::now('UTC')->subSeconds(60),
            'processed' => false,
        ]));

        $this->exec('workflow timeouts');

        $this->assertExitSuccess();
        $this->assertOutputContains('Processed: 0, Errors: 0');
        $this->assertErrorContains('Entity no longer exists');

        $processedTimeout = $timeoutsTable->get($timeoutsTable->find()->firstOrFail()->get('id'));
        $this->assertTrue((bool)$processedTimeout->get('processed'));
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
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', happy: true),
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
