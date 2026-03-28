<?php

declare(strict_types=1);

namespace Workflow\Engine;

use Cake\Datasource\EntityInterface;
use Workflow\Engine\Definition\Definition;

interface EngineInterface
{
    /**
     * Check if a transition can be applied to the entity.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context
     */
    public function can(
        Definition $definition,
        EntityInterface $entity,
        string $transition,
        array $context = [],
    ): bool;

    /**
     * Apply a transition to the entity.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context
     */
    public function apply(
        Definition $definition,
        EntityInterface $entity,
        string $transition,
        array $context = [],
    ): TransitionResult;

    /**
     * Get available transitions from the entity's current state.
     *
     * @return array<string>
     */
    public function getAvailableTransitions(
        Definition $definition,
        EntityInterface $entity,
    ): array;

    /**
     * Get the current state of the entity.
     */
    public function getCurrentState(
        Definition $definition,
        EntityInterface $entity,
    ): string;
}
