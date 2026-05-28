<?php

declare(strict_types=1);

namespace Workflow\Model;

use Cake\Datasource\EntityInterface;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\TransitionResult;

/**
 * Interface for tables with WorkflowBehavior attached.
 *
 * This interface documents the table-level workflow operations that services
 * can safely call on workflow-enabled tables.
 *
 * This interface enables proper static analysis of code that operates on
 * workflow-enabled tables.
 *
 * Note: Tables don't need to explicitly implement this interface; it's used
 * for type documentation. The WorkflowBatchService uses this for type safety.
 */
interface WorkflowTableInterface
{
    /**
     * Get the workflow definition.
     */
    public function getWorkflowDefinition(): Definition;

    /**
     * Apply a transition to the entity.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context
     */
    public function applyTransition(EntityInterface $entity, string $transition, array $context = []): TransitionResult;

    /**
     * Apply, persist, and orchestrate a transition.
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
    ): TransitionResult;

    /**
     * Check if a transition can be applied.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context
     */
    public function canTransition(EntityInterface $entity, string $transition, array $context = []): bool;
}
