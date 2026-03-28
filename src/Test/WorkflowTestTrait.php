<?php

declare(strict_types=1);

namespace Workflow\Test;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventManager;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Engine\StateMachineEngine;
use Workflow\Engine\TransitionResult;
use Workflow\Loader\LoaderInterface;
use Workflow\Service\WorkflowRegistry;

/**
 * Trait for testing workflows in application tests.
 */
trait WorkflowTestTrait
{
    /**
     * Create a simple test workflow definition.
     *
     * @param string $name
     * @param array<\Workflow\Engine\Definition\State> $states
     * @param array<\Workflow\Engine\Definition\Transition> $transitions
     */
    protected function createTestWorkflow(
        string $name = 'test',
        array $states = [],
        array $transitions = [],
    ): Definition {
        if (!$states) {
            $states = [
                new State('pending', initial: true),
                new State('approved'),
                new State('completed', final: true),
            ];
        }

        if (!$transitions) {
            $transitions = [
                new Transition('approve', ['pending'], 'approved'),
                new Transition('complete', ['approved'], 'completed'),
            ];
        }

        return new Definition(
            name: $name,
            table: 'Tests',
            field: 'state',
            states: $states,
            transitions: $transitions,
        );
    }

    /**
     * Create a workflow registry with a test definition.
     */
    protected function createTestRegistry(Definition $definition): WorkflowRegistry
    {
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

            /**
             * @return array<string>
             */
            public function getWorkflowNames(): array
            {
                return [$this->definition->getName()];
            }
        };

        return new WorkflowRegistry($loader, new EventManager());
    }

    /**
     * Assert that a transition can be applied to an entity.
     */
    protected function assertCanTransition(
        Definition $definition,
        EntityInterface $entity,
        string $transition,
        string $message = '',
    ): void {
        $engine = new StateMachineEngine(new EventManager());
        $can = $engine->can($definition, $entity, $transition);

        $this->assertTrue($can, $message ?: "Expected transition '{$transition}' to be allowed");
    }

    /**
     * Assert that a transition cannot be applied to an entity.
     */
    protected function assertCannotTransition(
        Definition $definition,
        EntityInterface $entity,
        string $transition,
        string $message = '',
    ): void {
        $engine = new StateMachineEngine(new EventManager());
        $can = $engine->can($definition, $entity, $transition);

        $this->assertFalse($can, $message ?: "Expected transition '{$transition}' to be blocked");
    }

    /**
     * Assert that an entity is in a specific state.
     */
    protected function assertInState(
        Definition $definition,
        EntityInterface $entity,
        string $expectedState,
        string $message = '',
    ): void {
        $engine = new StateMachineEngine(new EventManager());
        $currentState = $engine->getCurrentState($definition, $entity);

        $this->assertSame(
            $expectedState,
            $currentState,
            $message ?: "Expected entity to be in state '{$expectedState}', but was in '{$currentState}'",
        );
    }

    /**
     * Apply a transition and assert it succeeded.
     */
    protected function applyTransitionAndAssert(
        Definition $definition,
        EntityInterface $entity,
        string $transition,
        string $expectedState,
    ): TransitionResult {
        $engine = new StateMachineEngine(new EventManager());
        $result = $engine->apply($definition, $entity, $transition);

        $this->assertTrue($result->isSuccess(), "Transition '{$transition}' should have succeeded");
        $this->assertSame(
            $expectedState,
            $result->getToState(),
            "Transition should have resulted in state '{$expectedState}'",
        );

        return $result;
    }
}
