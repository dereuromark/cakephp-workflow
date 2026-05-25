<?php

declare(strict_types=1);

namespace Workflow\Event;

use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Workflow\Engine\Definition\Definition;

class WorkflowEvent extends Event
{
    /**
     * @var string
     */
    public const BEFORE_TRANSITION = 'Workflow.beforeTransition';

    /**
     * @var string
     */
    public const AFTER_TRANSITION = 'Workflow.afterTransition';

    /**
     * @var string
     */
    public const TRANSITION_BLOCKED = 'Workflow.transitionBlocked';

    /**
     * @var string
     */
    public const TRANSITION_ERROR = 'Workflow.transitionError';

    /**
     * @param string $name
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param string $fromState
     * @param string|null $toState
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $name,
        private readonly Definition $definition,
        private readonly EntityInterface $entity,
        private readonly string $transition,
        private readonly string $fromState,
        private readonly ?string $toState = null,
        private readonly array $context = [],
    ) {
        parent::__construct($name, $this);
    }

    public function getDefinition(): Definition
    {
        return $this->definition;
    }

    public function getEntity(): EntityInterface
    {
        return $this->entity;
    }

    public function getTransition(): string
    {
        return $this->transition;
    }

    public function getFromState(): string
    {
        return $this->fromState;
    }

    public function getToState(): ?string
    {
        return $this->toState;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
