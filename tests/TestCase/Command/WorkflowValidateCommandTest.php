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
        Configure::delete('Workflow.strictMode');

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

    public function testExecuteWarnsOnAutomaticBranchWithoutFallback(): void
    {
        $this->registerStuckBranchWorkflow();

        $this->exec('workflow validate');

        // Without strictMode it is only a warning - the run still succeeds.
        $this->assertExitCode(0);
        $this->assertErrorContains('Automatic branch states without a fallback');
        $this->assertOutputContains('evaluating');
    }

    public function testExecuteFailsOnAutomaticBranchWithoutFallbackInStrictMode(): void
    {
        Configure::write('Workflow.strictMode', true);
        $this->registerStuckBranchWorkflow();

        $this->exec('workflow validate');

        // With strictMode it is a hard error and the command exits non-zero.
        $this->assertExitCode(1);
        $this->assertErrorContains('Automatic branch states without a fallback');
        $this->assertOutputContains('evaluating');
    }

    /**
     * Registers a workflow whose only issue is a conditional-only automatic branch (no fallback,
     * no other exit) in the "evaluating" state.
     */
    private function registerStuckBranchWorkflow(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('evaluating'),
                new State('approved', final: true),
                new State('rejected', final: true),
            ],
            transitions: [
                new Transition('process', ['pending'], 'evaluating', happy: true),
                new Transition('auto_approve', ['evaluating'], 'approved', automatic: true, condition: 'isApproved'),
                new Transition('auto_reject', ['evaluating'], 'rejected', automatic: true, condition: 'isRejected'),
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
    }
}
