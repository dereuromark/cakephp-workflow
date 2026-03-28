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
     * @var array<string, callable>
     */
    private array $conditions = [];

    /**
     * @param \Cake\Event\EventManager $eventManager
     * @param array<string, callable> $guards
     * @param array<string, callable> $commands
     * @param bool $strictMode When true, throws exception for missing guards/commands/conditions
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

    /**
     * Register a condition callback for automatic branching.
     *
     * Conditions are evaluated when processing automatic transitions
     * to determine which branch to take. They should return true/false.
     */
    public function addCondition(string $name, callable $callback): void
    {
        $this->conditions[$name] = $callback;
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

        // Process automatic transitions from the new state
        $autoResult = $this->processAutomaticTransitions($definition, $entity, $context);
        if ($autoResult !== null) {
            // Return the final result after automatic transitions
            return TransitionResult::success($currentState, $autoResult->getToState() ?? $toState);
        }

        return TransitionResult::success($currentState, $toState);
    }

    /**
     * Process automatic transitions from the current state.
     *
     * Automatic transitions are evaluated without an explicit event trigger.
     * When multiple automatic transitions exist from a state, conditions
     * determine which one to take. The first transition with a passing
     * condition (or no condition) wins.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string, mixed> $context
     *
     * @return \Workflow\Engine\TransitionResult|null Result if automatic transition occurred, null otherwise
     */
    public function processAutomaticTransitions(
        Definition $definition,
        EntityInterface $entity,
        array $context = [],
    ): ?TransitionResult {
        $currentState = $this->getCurrentState($definition, $entity);
        $field = $definition->getField();

        // Get automatic transitions from current state
        $autoTransitions = $definition->getAutomaticTransitionsFromState($currentState);
        if (!$autoTransitions) {
            return null;
        }

        // Find the first matching transition (first with passing condition or no condition)
        $selectedTransition = null;
        foreach ($autoTransitions as $transition) {
            $condition = $transition->getCondition();

            if ($condition === null) {
                // No condition - this is the fallback/else branch
                // Only select if we haven't found a conditional match yet
                if ($selectedTransition === null) {
                    $selectedTransition = $transition;
                }

                continue;
            }

            // Evaluate condition
            if ($this->evaluateCondition($condition, $entity, $context)) {
                $selectedTransition = $transition;

                break; // First matching condition wins
            }
        }

        if ($selectedTransition === null) {
            return null; // No automatic transition to apply
        }

        $toState = $selectedTransition->getTo();
        $toStateObj = $definition->getState($toState);
        $transitionName = $selectedTransition->getName();

        // Fire before event for automatic transition
        $beforeEvent = new WorkflowEvent(
            WorkflowEvent::BEFORE_TRANSITION,
            $definition,
            $entity,
            $transitionName,
            $currentState,
            $toState,
            array_merge($context, ['automatic' => true]),
        );
        $this->eventManager->dispatch($beforeEvent);

        // Execute onExit for current state
        $currentStateObj = $definition->getState($currentState);
        try {
            $this->executeCallbacks($currentStateObj->getOnExit(), $entity, $context);
        } catch (Throwable $e) {
            return TransitionResult::error($currentState, $e);
        }

        // Execute commands for the automatic transition
        try {
            $this->executeCommands($selectedTransition->getCommands(), $entity, $context);
        } catch (Throwable $e) {
            return TransitionResult::error($currentState, $e);
        }

        // Apply the state change
        $entity->set($field, $toState);

        // Execute onEnter for new state
        try {
            $this->executeCallbacks($toStateObj->getOnEnter(), $entity, $context);
        } catch (Throwable $e) {
            return TransitionResult::error($currentState, $e);
        }

        // Fire after event
        $afterEvent = new WorkflowEvent(
            WorkflowEvent::AFTER_TRANSITION,
            $definition,
            $entity,
            $transitionName,
            $currentState,
            $toState,
            array_merge($context, ['automatic' => true]),
        );
        $this->eventManager->dispatch($afterEvent);

        // Recursively process further automatic transitions
        $furtherResult = $this->processAutomaticTransitions($definition, $entity, $context);
        if ($furtherResult !== null) {
            return $furtherResult;
        }

        return TransitionResult::success($currentState, $toState);
    }

    /**
     * Evaluate a condition callback.
     *
     * @param string $conditionName
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string, mixed> $context
     *
     * @throws \Workflow\Exception\WorkflowException When strict mode is enabled and condition is not found
     *
     * @return bool True if condition passes, false otherwise
     */
    private function evaluateCondition(string $conditionName, EntityInterface $entity, array $context): bool
    {
        if (!isset($this->conditions[$conditionName])) {
            if ($this->strictMode) {
                throw new WorkflowException(
                    "Condition '{$conditionName}' is not registered. "
                    . 'Check for typos in condition name or ensure the condition is properly configured.',
                );
            }

            // In non-strict mode, missing conditions evaluate to false
            return false;
        }

        return (bool)($this->conditions[$conditionName])($entity, $context);
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
