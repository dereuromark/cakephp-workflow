<?php

declare(strict_types=1);

namespace Workflow\Engine;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventManager;
use Closure;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;
use Workflow\Engine\Definition\Definition;
use Workflow\Event\WorkflowEvent;
use Workflow\Exception\CommandException;
use Workflow\Exception\WorkflowException;
use Workflow\State\AbstractState;

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
     * @param int $maxAutomaticTransitions Maximum automatic transitions to chain before aborting
     */
    public function __construct(
        private EventManager $eventManager,
        private array $guards = [],
        private array $commands = [],
        private bool $strictMode = false,
        private int $maxAutomaticTransitions = 10,
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

    public function setMaxAutomaticTransitions(int $maxAutomaticTransitions): void
    {
        $this->maxAutomaticTransitions = $maxAutomaticTransitions;
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
        $guardResult = $this->evaluateGuards($transitionObj->getGuards(), $entity, $context);

        return !$guardResult['blocked'];
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
        $guardResult = $this->evaluateGuards($transitionObj->getGuards(), $entity, $context);
        if ($guardResult['blocked']) {
            // Fire blocked event (safe - listener errors shouldn't affect blocked result)
            $blockedEvent = new WorkflowEvent(
                WorkflowEvent::TRANSITION_BLOCKED,
                $definition,
                $entity,
                $transition,
                $currentState,
                context: $context,
            );
            $this->safeDispatch($blockedEvent);

            return TransitionResult::blocked($currentState, $guardResult['blocked']);
        }

        $guardsEvaluated = $guardResult['evaluated'];

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
        $commandsExecuted = [];
        try {
            $commandsExecuted = $this->executeCommands($transitionObj->getCommands(), $entity, $context);
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
            $entity->set($field, $currentState);

            throw $e;
        } catch (Throwable $e) {
            $entity->set($field, $currentState);
            $this->dispatchErrorEvent($definition, $entity, $transition, $currentState, $toState, $context);

            return TransitionResult::error($currentState, $e);
        }

        // Fire after event (safe - listener errors shouldn't roll back the transition)
        $afterEvent = new WorkflowEvent(
            WorkflowEvent::AFTER_TRANSITION,
            $definition,
            $entity,
            $transition,
            $currentState,
            $toState,
            $context,
        );
        $this->safeDispatch($afterEvent);

        // Process automatic transitions from the new state
        $autoResult = $this->processAutomaticTransitions($definition, $entity, $context);
        if ($autoResult !== null) {
            // Return the final result after automatic transitions, merging runtime data
            return TransitionResult::success(
                $currentState,
                $autoResult->getToState() ?? $toState,
                array_merge($guardsEvaluated, $autoResult->getGuardsEvaluated()),
                array_merge($commandsExecuted, $autoResult->getCommandsExecuted()),
                $autoResult->usedLock(),
            );
        }

        return TransitionResult::success(
            $currentState,
            $toState,
            $guardsEvaluated,
            $commandsExecuted,
        );
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
        return $this->processAutomaticTransitionsInternal($definition, $entity, $context, 0);
    }

    private function processAutomaticTransitionsInternal(
        Definition $definition,
        EntityInterface $entity,
        array $context,
        int $processedCount,
    ): ?TransitionResult {
        if ($processedCount >= $this->maxAutomaticTransitions) {
            throw new WorkflowException(
                sprintf(
                    'Exceeded automatic transition limit of %d for workflow %s. '
                    . 'This usually indicates a transition cycle.',
                    $this->maxAutomaticTransitions,
                    $definition->getName(),
                ),
            );
        }

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
        $commandsExecuted = [];
        try {
            $this->executeCallbacks($currentStateObj->getOnExit(), $entity, $context);
            $commandsExecuted = $this->executeCommands($selectedTransition->getCommands(), $entity, $context);
        } catch (Throwable $e) {
            $this->dispatchErrorEvent($definition, $entity, $transitionName, $currentState, $toState, $context);

            return TransitionResult::error($currentState, $e);
        }

        // Apply the state change
        $entity->set($field, $toState);

        // Execute onEnter for new state
        try {
            $this->executeCallbacks($toStateObj->getOnEnter(), $entity, $context);
        } catch (Throwable $e) {
            $entity->set($field, $currentState);
            $this->dispatchErrorEvent($definition, $entity, $transitionName, $currentState, $toState, $context);

            return TransitionResult::error($currentState, $e);
        }

        // Fire after event (safe - listener errors shouldn't roll back the transition)
        $afterEvent = new WorkflowEvent(
            WorkflowEvent::AFTER_TRANSITION,
            $definition,
            $entity,
            $transitionName,
            $currentState,
            $toState,
            array_merge($context, ['automatic' => true]),
        );
        $this->safeDispatch($afterEvent);

        // Recursively process further automatic transitions
        $furtherResult = $this->processAutomaticTransitionsInternal($definition, $entity, $context, $processedCount + 1);
        if ($furtherResult !== null) {
            // Merge commands from this transition with further results
            return TransitionResult::success(
                $currentState,
                $furtherResult->getToState() ?? $toState,
                $furtherResult->getGuardsEvaluated(),
                array_merge($commandsExecuted, $furtherResult->getCommandsExecuted()),
                $furtherResult->usedLock(),
            );
        }

        return TransitionResult::success(
            $currentState,
            $toState,
            [], // No guards for automatic transitions
            $commandsExecuted,
        );
    }

    /**
     * Evaluate a condition callback.
     *
     * Conditions that throw exceptions are treated as returning false (condition not met).
     * This prevents buggy conditions from crashing automatic transition processing.
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

        try {
            return (bool)($this->conditions[$conditionName])($entity, $context);
        } catch (WorkflowException $e) {
            // Configuration errors should propagate
            throw $e;
        } catch (Throwable $e) {
            // Condition runtime exceptions are treated as condition not met
            // Log or handle as needed - for now, silently return false
            if ($this->strictMode) {
                throw new WorkflowException(
                    "Condition '{$conditionName}' threw exception: " . $e->getMessage(),
                    0,
                    $e,
                );
            }

            return false;
        }
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
        $this->safeDispatch($errorEvent);
    }

    /**
     * Safely dispatch an event, catching listener exceptions.
     *
     * Exceptions from event listeners should not crash the transition process,
     * especially for non-critical events like AFTER_TRANSITION or ERROR events.
     *
     * @param \Workflow\Event\WorkflowEvent $event
     * @param bool $throwOnError If true, rethrows exceptions; if false, silently ignores them
     *
     * @throws \Throwable When $throwOnError is true and a listener throws
     */
    private function safeDispatch(WorkflowEvent $event, bool $throwOnError = false): void
    {
        try {
            $this->eventManager->dispatch($event);
        } catch (Throwable $e) {
            if ($throwOnError) {
                throw $e;
            }
            // For non-critical events, swallow the exception
            // In production, you might want to log this
        }
    }

    /**
     * Execute lifecycle callbacks (onEnter/onExit).
     *
     * @param array<string> $callbackNames
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string, mixed> $context
     */
    private function executeCallbacks(array $callbackNames, EntityInterface $entity, array $context): void
    {
        foreach ($callbackNames as $callbackName) {
            $handler = $this->resolveHandler($callbackName, $this->commands);
            if ($handler === null) {
                $this->throwIfStrict("Lifecycle callback '{$callbackName}' is not registered.");

                continue;
            }

            $this->invokeHandler($handler, $entity, $context);
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
        $availableTransitions = [];

        foreach ($transitions as $transition) {
            if ($this->can($definition, $entity, $transition->getName())) {
                $availableTransitions[] = $transition->getName();
            }
        }

        return $availableTransitions;
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
     * Evaluate guards and return both blocked guards and all evaluated guards.
     *
     * Guards that throw exceptions are treated as blocking the transition.
     *
     * @param array<string> $guardNames
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string, mixed> $context
     *
     * @return array{blocked: array<string, string>, evaluated: array<string>}
     */
    private function evaluateGuards(array $guardNames, EntityInterface $entity, array $context): array
    {
        $blocked = [];
        $evaluated = [];

        foreach ($guardNames as $guardName) {
            $handler = $this->resolveHandler($guardName, $this->guards);
            if ($handler === null) {
                $this->throwIfStrict(
                    "Guard '{$guardName}' is not registered. Check for typos in guard name or ensure the guard is properly configured.",
                );

                continue;
            }

            $evaluated[] = $guardName;

            try {
                $result = $this->invokeHandler($handler, $entity, $context);
                if ($result !== true) {
                    $blocked[$guardName] = is_string($result) ? $result : "Guard '{$guardName}' returned false";
                }
            } catch (WorkflowException $e) {
                // Configuration errors should propagate
                throw $e;
            } catch (Throwable $e) {
                // Guard runtime exceptions block the transition with the error message
                $blocked[$guardName] = "Guard '{$guardName}' threw exception: " . $e->getMessage();
            }
        }

        return ['blocked' => $blocked, 'evaluated' => $evaluated];
    }

    /**
     * Execute command callbacks.
     *
     * @param array<string> $commandNames
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string, mixed> $context
     *
     * @throws \Workflow\Exception\CommandException When command execution fails
     *
     * @return array<string> List of commands that were executed
     */
    private function executeCommands(array $commandNames, EntityInterface $entity, array $context): array
    {
        $executed = [];

        foreach ($commandNames as $commandName) {
            try {
                $handler = $this->resolveHandler($commandName, $this->commands);
                if ($handler === null) {
                    $this->throwIfStrict(
                        "Command '{$commandName}' is not registered. Check for typos in command name or ensure the command is properly configured.",
                    );

                    continue;
                }

                $this->invokeHandler($handler, $entity, $context);
                $executed[] = $commandName;
            } catch (WorkflowException $e) {
                throw $e;
            } catch (Throwable $e) {
                throw new CommandException($commandName, $e);
            }
        }

        return $executed;
    }

    private function resolveHandler(string $handlerName, array $registeredHandlers): callable|string|null
    {
        if (isset($registeredHandlers[$handlerName])) {
            return $registeredHandlers[$handlerName];
        }

        if (str_contains($handlerName, '::')) {
            return $handlerName;
        }

        return null;
    }

    private function invokeHandler(callable|string $handler, EntityInterface $entity, array $context): mixed
    {
        if (is_string($handler)) {
            [$className, $methodName] = explode('::', $handler, 2);
            $reflection = new ReflectionMethod($className, $methodName);

            if ($reflection->isStatic()) {
                return $this->invokeCallableWithSupportedArguments(
                    [$className, $methodName],
                    $reflection->getNumberOfParameters(),
                    $entity,
                    $context,
                );
            }

            $instance = new $className();
            if ($instance instanceof AbstractState) {
                $instance->bind($entity, $context);
            }

            return $this->invokeCallableWithSupportedArguments(
                [$instance, $methodName],
                $reflection->getNumberOfParameters(),
                $entity,
                $context,
            );
        }

        return $this->invokeCallableWithSupportedArguments(
            $handler,
            $this->getCallableParameterCount($handler),
            $entity,
            $context,
        );
    }

    private function invokeCallableWithSupportedArguments(
        mixed $callable,
        int $parameterCount,
        EntityInterface $entity,
        array $context,
    ): mixed {
        if (!is_callable($callable)) {
            throw new WorkflowException('Resolved handler is not callable.');
        }

        return match (true) {
            $parameterCount <= 0 => $callable(),
            $parameterCount === 1 => $callable($entity),
            default => $callable($entity, $context),
        };
    }

    private function getCallableParameterCount(mixed $callable): int
    {
        if (is_array($callable)) {
            return (new ReflectionMethod($callable[0], $callable[1]))->getNumberOfParameters();
        }

        if (is_object($callable) && !$callable instanceof Closure) {
            return (new ReflectionMethod($callable, '__invoke'))->getNumberOfParameters();
        }

        return (new ReflectionFunction($callable))->getNumberOfParameters();
    }

    private function throwIfStrict(string $message): void
    {
        if ($this->strictMode) {
            throw new WorkflowException($message);
        }
    }
}
