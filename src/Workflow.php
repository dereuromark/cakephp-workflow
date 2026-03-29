<?php
declare(strict_types=1);

namespace Workflow;

use Cake\Datasource\EntityInterface;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\EngineInterface;
use Workflow\Engine\TransitionResult;

/**
 * Workflow object providing a clean API for workflow operations on an entity.
 *
 * Usage:
 * ```php
 * $workflow = $registry->get($order);
 * if ($workflow->can('pay')) {
 *     $result = $workflow->apply('pay', ['user_id' => $userId]);
 * }
 * ```
 */
class Workflow
{
    public function __construct(
        private Definition $definition,
        private EntityInterface $entity,
        private EngineInterface $engine,
    ) {
    }

    /**
     * Check if a transition can be applied.
     *
     * @param string $transition Transition name
     * @param array<string, mixed> $context Optional context data
     * @return bool True if transition is allowed
     */
    public function can(string $transition, array $context = []): bool
    {
        return $this->engine->can($this->definition, $this->entity, $transition, $context);
    }

    /**
     * Apply a transition to the entity.
     *
     * @param string $transition Transition name
     * @param array<string, mixed> $context Optional context data
     * @return \Workflow\Engine\TransitionResult Result of the transition attempt
     */
    public function apply(string $transition, array $context = []): TransitionResult
    {
        return $this->engine->apply($this->definition, $this->entity, $transition, $context);
    }

    /**
     * Get the current state name.
     *
     * @return string Current state name
     */
    public function getStateName(): string
    {
        return $this->engine->getCurrentState($this->definition, $this->entity);
    }

    /**
     * Get the current state object.
     *
     * @return \Workflow\Engine\Definition\State Current state
     */
    public function getState(): State
    {
        return $this->definition->getState($this->getStateName());
    }

    /**
     * Get transitions that are currently enabled (passing all guards).
     *
     * @param array<string, mixed> $context Optional context for guard evaluation
     * @return array<string> List of enabled transition names
     */
    public function getEnabledTransitions(array $context = []): array
    {
        $stateName = $this->getStateName();
        $transitions = $this->definition->getTransitionsFromState($stateName);
        $enabled = [];

        foreach ($transitions as $transition) {
            if ($this->can($transition->getName(), $context)) {
                $enabled[] = $transition->getName();
            }
        }

        return $enabled;
    }

    /**
     * Get all transitions available from current state (regardless of guards).
     *
     * @return array<string> List of transition names
     */
    public function getAvailableTransitions(): array
    {
        $stateName = $this->getStateName();
        $transitions = $this->definition->getTransitionsFromState($stateName);

        return array_map(fn ($t) => $t->getName(), $transitions);
    }

    /**
     * Check if entity is in a specific state.
     *
     * @param string $state State name to check
     * @return bool True if entity is in the specified state
     */
    public function isInState(string $state): bool
    {
        return $this->getStateName() === $state;
    }

    /**
     * Check if entity is in a final state.
     *
     * @return bool True if entity is in a final state
     */
    public function isInFinalState(): bool
    {
        return $this->getState()->isFinal();
    }

    /**
     * Check if entity has a specific flag on current state.
     *
     * @param string $flag Flag name
     * @return bool True if current state has the flag
     */
    public function hasFlag(string $flag): bool
    {
        return $this->getState()->hasFlag($flag);
    }

    /**
     * Get the workflow definition.
     *
     * @return \Workflow\Engine\Definition\Definition
     */
    public function getDefinition(): Definition
    {
        return $this->definition;
    }

    /**
     * Get the entity.
     *
     * @return \Cake\Datasource\EntityInterface
     */
    public function getEntity(): EntityInterface
    {
        return $this->entity;
    }

    /**
     * Get the workflow name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->definition->getName();
    }
}
