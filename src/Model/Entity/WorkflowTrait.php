<?php

declare(strict_types=1);

namespace Workflow\Model\Entity;

use Cake\ORM\Locator\LocatorAwareTrait;
use Workflow\Exception\WorkflowException;
use Workflow\Model\Behavior\WorkflowBehavior;

/**
 * Read-only convenience for entities whose table uses the Workflow behavior:
 * `$order->currentState()`, `$order->canTransition('pay')`, `$order->availableTransitions()`.
 *
 * Mutating transitions stay on the table (they need save + lock + transaction),
 * so this trait deliberately exposes only inspection helpers.
 *
 * @mixin \Cake\ORM\Entity
 */
trait WorkflowTrait
{
    use LocatorAwareTrait;

    /**
     * The entity's current workflow state.
     */
    public function currentState(): string
    {
        return $this->workflowBehavior()->getCurrentState($this);
    }

    /**
     * Whether the given transition can currently be applied to this entity.
     *
     * @param string $transition
     * @param array<string, mixed> $context
     */
    public function canTransition(string $transition, array $context = []): bool
    {
        return $this->workflowBehavior()->canTransition($this, $transition, $context);
    }

    /**
     * Transition names currently available from this entity's state.
     *
     * @return array<string>
     */
    public function availableTransitions(): array
    {
        return $this->workflowBehavior()->getAvailableTransitions($this);
    }

    /**
     * Whether the entity is in the given state.
     */
    public function isInState(string $state): bool
    {
        return $this->workflowBehavior()->isInState($this, $state);
    }

    /**
     * Whether the entity's current state is a final state.
     */
    public function isFinalState(): bool
    {
        return $this->workflowBehavior()->isFinal($this);
    }

    /**
     * Whether the entity's current state carries the given flag.
     */
    public function hasStateFlag(string $flag): bool
    {
        return $this->workflowBehavior()->hasFlag($this, $flag);
    }

    /**
     * Resolve the Workflow behavior from this entity's source table.
     *
     * @throws \Workflow\Exception\WorkflowException When the entity has no source
     *   table set (e.g. created with `new Entity()` outside the ORM).
     */
    protected function workflowBehavior(): WorkflowBehavior
    {
        $source = $this->getSource();
        if ($source === '') {
            throw new WorkflowException(
                'Cannot resolve the workflow behavior: this entity has no source table set.',
            );
        }

        $table = $this->fetchTable($source);
        /** @var \Workflow\Model\Behavior\WorkflowBehavior $behavior */
        $behavior = $table->getBehavior('Workflow');

        return $behavior;
    }
}
