<?php

declare(strict_types=1);

namespace Workflow\Model\Table;

use Cake\Datasource\EntityInterface;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\TransitionResult;
use Workflow\Model\Behavior\WorkflowBehavior;

/**
 * Convenience trait for tables that use the Workflow behavior.
 *
 * Add it to a table that has `addBehavior('Workflow.Workflow', ...)` to get
 * statically-typed access to the most common workflow operations directly on the
 * table — `$this->Orders->transition($order, 'pay')` — without reaching through
 * `getBehavior()` or tripping static analysis.
 *
 * @mixin \Cake\ORM\Table
 */
trait WorkflowTableTrait
{
    /**
     * The Workflow behavior attached to this table.
     */
    public function workflowBehavior(): WorkflowBehavior
    {
        /** @var \Workflow\Model\Behavior\WorkflowBehavior $behavior */
        $behavior = $this->getBehavior('Workflow');

        return $behavior;
    }

    /**
     * Get the workflow definition for this table.
     */
    public function getWorkflowDefinition(): Definition
    {
        return $this->workflowBehavior()->getWorkflowDefinition();
    }

    /**
     * Apply and persist a transition (save + log + lock + transaction).
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context
     * @param array{save?: bool, log?: bool, lock?: bool|null, timeouts?: bool|null, transaction?: bool} $options
     */
    public function transition(
        EntityInterface $entity,
        string $transition,
        array $context = [],
        array $options = [],
    ): TransitionResult {
        return $this->workflowBehavior()->transition($entity, $transition, $context, $options);
    }

    /**
     * Apply a transition without transition()'s orchestrated save + log. Locking
     * still applies when it is enabled for the behavior.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context
     */
    public function applyTransition(EntityInterface $entity, string $transition, array $context = []): TransitionResult
    {
        return $this->workflowBehavior()->applyTransition($entity, $transition, $context);
    }

    /**
     * Whether the transition can currently be applied to the entity.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context
     */
    public function canTransition(EntityInterface $entity, string $transition, array $context = []): bool
    {
        return $this->workflowBehavior()->canTransition($entity, $transition, $context);
    }

    /**
     * Transition names currently available from the entity's state.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     *
     * @return array<string>
     */
    public function availableTransitions(EntityInterface $entity): array
    {
        return $this->workflowBehavior()->getAvailableTransitions($entity);
    }

    /**
     * Transition names currently available from the entity's state.
     *
     * Interface-mandated alias of {@see availableTransitions()}.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     *
     * @return array<string>
     */
    public function getAvailableTransitions(EntityInterface $entity): array
    {
        return $this->workflowBehavior()->getAvailableTransitions($entity);
    }

    /**
     * The entity's current workflow state.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     */
    public function currentState(EntityInterface $entity): string
    {
        return $this->workflowBehavior()->getCurrentState($entity);
    }

    /**
     * The entity's current workflow state.
     *
     * Interface-mandated alias of {@see currentState()}.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     */
    public function getCurrentState(EntityInterface $entity): string
    {
        return $this->workflowBehavior()->getCurrentState($entity);
    }
}
