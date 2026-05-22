<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Loader\LoaderInterface;
use Workflow\Model\Behavior\WorkflowBehavior;
use Workflow\Service\WorkflowRegistry;
use Workflow\Test\TestCase\DatabaseTestCase;

class WorkflowValidateCommandTest extends DatabaseTestCase
{
    use ConsoleIntegrationTestTrait;

    public function tearDown(): void
    {
        Configure::delete('Workflow.registry');

        parent::tearDown();
    }

    public function testExecuteReportsTransitionsFromTerminalStates(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('delivered', final: true),
                new State('refunded', final: true),
            ],
            transitions: [
                new Transition('deliver', ['pending'], 'delivered', happy: true),
                new Transition('refund', ['delivered'], 'refunded'),
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

        Configure::write('Workflow.registry', new WorkflowRegistry($loader, new EventManager()));

        $this->exec('workflow validate');

        $this->assertExitCode(1);
        $this->assertErrorContains('Transitions from terminal states:');
        $this->assertOutputContains('refund from final state delivered');
    }

    public function testCheckDataReportsStaleAndUnversionedRecords(): void
    {
        $this->truncateTables();

        $definition = new Definition(
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
                new Transition('pay', ['pending'], 'paid', happy: true),
                new Transition('ship', ['paid'], 'shipped'),
                new Transition('complete', ['shipped'], 'completed'),
            ],
        );

        $hash = $definition->getVersionHash();
        $this->insertOrder('paid', 'outdated'); // stale
        $this->insertOrder('pending', null); // unversioned
        $this->insertOrder('shipped', $hash); // current

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

        $registry = new WorkflowRegistry($loader, new EventManager());
        Configure::write('Workflow.registry', $registry);

        $this->getTableLocator()->get('Orders')->addBehavior('Workflow', [
            'className' => WorkflowBehavior::class,
            'workflow' => 'order',
            'registry' => $registry,
            'versioning' => true,
        ]);

        $this->exec('workflow validate order --check-data');

        $this->assertExitSuccess();
        $this->assertErrorContains('stale');
        $this->assertErrorContains('unversioned');
        $this->assertOutputNotContains('No issues found');
    }

    public function testCheckDataDoesNotReportDriftWhenVersioningDisabled(): void
    {
        $this->truncateTables();

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
                new State('completed', final: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', happy: true),
                new Transition('complete', ['paid'], 'completed'),
            ],
        );

        $this->insertOrder('pending', null);

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

        Configure::write('Workflow.registry', new WorkflowRegistry($loader, new EventManager()));

        // No Workflow behavior attached / versioning not enabled on the table.
        $this->exec('workflow validate order --check-data');

        $this->assertExitSuccess();
        $this->assertOutputContains('No issues found');
    }

    private function insertOrder(string $state, ?string $version): void
    {
        ConnectionManager::get('test')->execute(
            'INSERT INTO orders (state, workflow_version) VALUES (?, ?)',
            [$state, $version],
        );
    }
}
