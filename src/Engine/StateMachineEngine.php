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
     * @param \Cake\Event\EventManager $eventManager
     * @param array<string, callable> $guards
     * @param array<string, callable> $commands
     * @param bool $strictMode When true, throws exception for missing guards/commands
     */
    public function __construct(
        private EventManager $eventManager,
        private array $guards = [],
        private array $commands = [],
        private bool $strictMode = false,
    ) {
    }

    /**
     * Enable or disable strict mode.
     *
     * When enabled, missing guards or commands will throw an exception
     * instead of being silently ignored.
     */
    public function setStrictMode(bool $strict): void
    {
        $this->strictMode = $strict;
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

        return !$blockedBy;
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

        // Check if reason is required for this transition
        if ($stateObj->requiresReasonFor($transition)) {
            $reason = $context['reason'] ?? null;
            if ($reason === null || (is_string($reason) && trim($reason) === '')) {
                return TransitionResult::blocked(
                    $currentState,
                    ['reason' => "Transition '{$transition}' requires a reason. Provide 'reason' in context."],
                );
            }
        }

        // Check guards
        $blockedBy = $this->evaluateGuards($transitionObj->getGuards(), $entity, $context);
        if ($blockedBy) {
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
        $toStateObj = $definition->getState($toState);

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

        // Execute onExit callbacks for current state
        try {
            $this->executeCallbacks($stateObj->getOnExit(), $entity, $context);
        } catch (WorkflowException $e) {
            // Configuration errors should propagate (e.g., missing callbacks in strict mode)
            throw $e;
        } catch (Throwable $e) {
            $this->dispatchErrorEvent($definition, $entity, $transition, $currentState, $toState, $context);

            return TransitionResult::error($currentState, $e);
        }

        // Execute commands
        try {
            $this->executeCommands($transitionObj->getCommands(), $entity, $context);
        } catch (CommandException $e) {
            // Command runtime failures should return error result
            $this->dispatchErrorEvent($definition, $entity, $transition, $currentState, $toState, $context);

            return TransitionResult::error($currentState, $e);
        } catch (WorkflowException $e) {
            // Configuration errors should propagate (e.g., missing commands in strict mode)
            throw $e;
        } catch (Throwable $e) {
            $this->dispatchErrorEvent($definition, $entity, $transition, $currentState, $toState, $context);

            return TransitionResult::error($currentState, $e);
        }

        // Apply the transition
        $entity->set($field, $toState);

        // Execute onEnter callbacks for new state
        try {
            $this->executeCallbacks($toStateObj->getOnEnter(), $entity, $context);
        } catch (WorkflowException $e) {
            // Configuration errors should propagate (e.g., missing callbacks in strict mode)
            throw $e;
        } catch (Throwable $e) {
            // Note: State was already changed, but we report the error
            $this->dispatchErrorEvent($definition, $entity, $transition, $currentState, $toState, $context);

            return TransitionResult::error($currentState, $e);
        }

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
     * Dispatch error event for transition failures.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param string $fromState
     * @param string $toState
     * @param array<string, mixed> $context
     */
    private function dispatchErrorEvent(
        Definition $definition,
        EntityInterface $entity,
        string $transition,
        string $fromState,
        string $toState,
        array $context,
    ): void {
        $errorEvent = new WorkflowEvent(
            WorkflowEvent::TRANSITION_ERROR,
            $definition,
            $entity,
            $transition,
            $fromState,
            $toState,
            $context,
        );
        $this->eventManager->dispatch($errorEvent);
    }

    /**
     * Execute lifecycle callbacks (onEnter/onExit).
     *
     * @param array<string> $callbackNames
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string, mixed> $context
     *
     * @throws \Workflow\Exception\WorkflowException When strict mode is enabled and callback is not found
     */
    private function executeCallbacks(array $callbackNames, EntityInterface $entity, array $context): void
    {
        foreach ($callbackNames as $callbackName) {
            if (!isset($this->commands[$callbackName])) {
                if ($this->strictMode) {
                    throw new WorkflowException("Lifecycle callback '{$callbackName}' is not registered.");
                }

                continue;
            }

            ($this->commands[$callbackName])($entity, $context);
        }
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
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string, mixed> $context
     *
     * @throws \Workflow\Exception\WorkflowException When strict mode is enabled and guard is not found
     *
     * @return array<string, string>
     */
    private function evaluateGuards(array $guardNames, EntityInterface $entity, array $context): array
    {
        $blocked = [];

        foreach ($guardNames as $guardName) {
            if (!isset($this->guards[$guardName])) {
                if ($this->strictMode) {
                    throw new WorkflowException("Guard '{$guardName}' is not registered. Check for typos in guard name or ensure the guard is properly configured.");
                }

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
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string, mixed> $context
     *
     * @throws \Workflow\Exception\WorkflowException When strict mode is enabled and command is not found
     * @throws \Workflow\Exception\CommandException When command execution fails
     */
    private function executeCommands(array $commandNames, EntityInterface $entity, array $context): void
    {
        foreach ($commandNames as $commandName) {
            if (!isset($this->commands[$commandName])) {
                if ($this->strictMode) {
                    throw new WorkflowException("Command '{$commandName}' is not registered. Check for typos in command name or ensure the command is properly configured.");
                }

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
