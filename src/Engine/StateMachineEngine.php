<?php
declare(strict_types=1);

namespace Workflow\Engine;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventManager;
use Throwable;
use Workflow\Engine\Definition\Definition;
use Workflow\Event\WorkflowEvent;
use Workflow\Exception\CommandException;
use Workflow\Exception\WorkflowException;

class StateMachineEngine implements EngineInterface
{
    /**
     * @param array<string, callable> $guards
     * @param array<string, callable> $commands
     */
    public function __construct(
        private EventManager $eventManager,
        private array $guards = [],
        private array $commands = [],
    ) {
    }

    /**
     * Register a guard callback.
     */
    public function addGuard(string $name, callable $callback): void
    {
        $this->guards[$name] = $callback;
    }

    /**
     * Register a command callback.
     */
    public function addCommand(string $name, callable $callback): void
    {
        $this->commands[$name] = $callback;
    }

    public function can(
        Definition $definition,
        EntityInterface $entity,
        string $transition,
        array $context = [],
    ): bool {
        $currentState = $this->getCurrentState($definition, $entity);

        // Check if current state is final
        $stateObj = $definition->getState($currentState);
        if ($stateObj->isFinal()) {
            return false;
        }

        // Check if transition exists and is allowed from current state
        try {
            $transitionObj = $definition->getTransition($transition);
        } catch (WorkflowException) {
            return false;
        }

        if (!$transitionObj->isAllowedFrom($currentState)) {
            return false;
        }

        // Check guards
        $blockedBy = $this->evaluateGuards($transitionObj->getGuards(), $entity, $context);

        return empty($blockedBy);
    }

    public function apply(
        Definition $definition,
        EntityInterface $entity,
        string $transition,
        array $context = [],
    ): TransitionResult {
        $currentState = $this->getCurrentState($definition, $entity);
        $field = $definition->getField();

        // Check if current state is final
        $stateObj = $definition->getState($currentState);
        if ($stateObj->isFinal()) {
            return TransitionResult::blocked(
                $currentState,
                ['state' => "Cannot transition from final state '{$currentState}'"],
            );
        }

        // Check if transition exists
        try {
            $transitionObj = $definition->getTransition($transition);
        } catch (WorkflowException) {
            return TransitionResult::blocked(
                $currentState,
                ['transition' => "Transition '{$transition}' not found"],
            );
        }

        // Check if allowed from current state
        if (!$transitionObj->isAllowedFrom($currentState)) {
            return TransitionResult::blocked(
                $currentState,
                ['transition' => "Transition '{$transition}' not allowed from state '{$currentState}'"],
            );
        }

        // Check guards
        $blockedBy = $this->evaluateGuards($transitionObj->getGuards(), $entity, $context);
        if (!empty($blockedBy)) {
            // Fire blocked event
            $blockedEvent = new WorkflowEvent(
                WorkflowEvent::TRANSITION_BLOCKED,
                $definition,
                $entity,
                $transition,
                $currentState,
                context: $context,
            );
            $this->eventManager->dispatch($blockedEvent);

            return TransitionResult::blocked($currentState, $blockedBy);
        }

        $toState = $transitionObj->getTo();

        // Fire before event
        $beforeEvent = new WorkflowEvent(
            WorkflowEvent::BEFORE_TRANSITION,
            $definition,
            $entity,
            $transition,
            $currentState,
            $toState,
            $context,
        );
        $this->eventManager->dispatch($beforeEvent);

        // Execute commands
        try {
            $this->executeCommands($transitionObj->getCommands(), $entity, $context);
        } catch (Throwable $e) {
            // Fire error event
            $errorEvent = new WorkflowEvent(
                WorkflowEvent::TRANSITION_ERROR,
                $definition,
                $entity,
                $transition,
                $currentState,
                $toState,
                $context,
            );
            $this->eventManager->dispatch($errorEvent);

            return TransitionResult::error($currentState, $e);
        }

        // Apply the transition
        $entity->set($field, $toState);

        // Fire after event
        $afterEvent = new WorkflowEvent(
            WorkflowEvent::AFTER_TRANSITION,
            $definition,
            $entity,
            $transition,
            $currentState,
            $toState,
            $context,
        );
        $this->eventManager->dispatch($afterEvent);

        return TransitionResult::success($currentState, $toState);
    }

    /**
     * @return array<string>
     */
    public function getAvailableTransitions(
        Definition $definition,
        EntityInterface $entity,
    ): array {
        $currentState = $this->getCurrentState($definition, $entity);

        // Check if current state is final
        $stateObj = $definition->getState($currentState);
        if ($stateObj->isFinal()) {
            return [];
        }

        $transitions = $definition->getTransitionsFromState($currentState);

        return array_map(
            fn ($t) => $t->getName(),
            $transitions,
        );
    }

    public function getCurrentState(
        Definition $definition,
        EntityInterface $entity,
    ): string {
        $field = $definition->getField();
        $state = $entity->get($field);

        if ($state === null || $state === '') {
            return $definition->getInitialState()->getName();
        }

        return $state;
    }

    /**
     * Evaluate guards and return any that blocked.
     *
     * @param array<string> $guardNames
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    private function evaluateGuards(array $guardNames, EntityInterface $entity, array $context): array
    {
        $blocked = [];

        foreach ($guardNames as $guardName) {
            if (!isset($this->guards[$guardName])) {
                continue;
            }

            $result = ($this->guards[$guardName])($entity, $context);
            if ($result !== true) {
                $blocked[$guardName] = is_string($result) ? $result : "Guard '{$guardName}' returned false";
            }
        }

        return $blocked;
    }

    /**
     * Execute command callbacks.
     *
     * @param array<string> $commandNames
     * @param array<string, mixed> $context
     */
    private function executeCommands(array $commandNames, EntityInterface $entity, array $context): void
    {
        foreach ($commandNames as $commandName) {
            if (!isset($this->commands[$commandName])) {
                continue;
            }

            try {
                ($this->commands[$commandName])($entity, $context);
            } catch (Throwable $e) {
                throw new CommandException($commandName, $e);
            }
        }
    }
}
