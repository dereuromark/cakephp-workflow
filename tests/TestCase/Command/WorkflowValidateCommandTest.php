<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Loader\LoaderInterface;
use Workflow\Service\WorkflowRegistry;

class WorkflowValidateCommandTest extends TestCase
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
}
