<?php
declare(strict_types=1);

namespace Workflow\Event;

use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Workflow\Engine\Definition\Definition;

class WorkflowEvent extends Event
{
    public const BEFORE_TRANSITION = 'Workflow.beforeTransition';
    public const AFTER_TRANSITION = 'Workflow.afterTransition';
    public const TRANSITION_BLOCKED = 'Workflow.transitionBlocked';
    public const TRANSITION_ERROR = 'Workflow.transitionError';

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $name,
        private Definition $definition,
        private EntityInterface $entity,
        private string $transition,
        private string $fromState,
        private ?string $toState = null,
        private array $context = [],
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
