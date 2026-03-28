<?php

declare(strict_types=1);

namespace Workflow\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Behavior;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\EngineInterface;
use Workflow\Engine\TransitionResult;
use Workflow\Exception\WorkflowException;
use Workflow\Service\WorkflowRegistry;

class WorkflowBehavior extends Behavior
{
    protected array $_defaultConfig = [
        'workflow' => null,
        'registry' => null,
        'field' => null,
    ];

    private ?Definition $definition = null;

    private ?EngineInterface $engine = null;

    public function initialize(array $config): void
    {
        parent::initialize($config);

        if ($this->getConfig('workflow') === null) {
            throw new WorkflowException('WorkflowBehavior requires a workflow name');
        }
    }

    /**
     * Get the workflow definition.
     */
    public function getWorkflowDefinition(): Definition
    {
        if ($this->definition === null) {
            $registry = $this->getRegistry();
            $this->definition = $registry->getWorkflow($this->getConfig('workflow'));
        }

        return $this->definition;
    }

    /**
     * Get the workflow engine.
     */
    protected function getWorkflowEngine(): EngineInterface
    {
        if ($this->engine === null) {
            $registry = $this->getRegistry();
            $this->engine = $registry->getEngine($this->getConfig('workflow'));
        }

        return $this->engine;
    }

    /**
     * Check if a transition can be applied.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context
     */
    public function canTransition(EntityInterface $entity, string $transition, array $context = []): bool
    {
        return $this->getWorkflowEngine()->can(
            $this->getWorkflowDefinition(),
            $entity,
            $transition,
            $context,
        );
    }

    /**
     * Apply a transition to the entity.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context
     */
    public function applyTransition(EntityInterface $entity, string $transition, array $context = []): TransitionResult
    {
        return $this->getWorkflowEngine()->apply(
            $this->getWorkflowDefinition(),
            $entity,
            $transition,
            $context,
        );
    }

    /**
     * Get available transitions for the entity.
     *
     * @return array<string>
     */
    public function getAvailableTransitions(EntityInterface $entity): array
    {
        return $this->getWorkflowEngine()->getAvailableTransitions(
            $this->getWorkflowDefinition(),
            $entity,
        );
    }

    /**
     * Get the current state of the entity.
     */
    public function getCurrentState(EntityInterface $entity): string
    {
        return $this->getWorkflowEngine()->getCurrentState(
            $this->getWorkflowDefinition(),
            $entity,
        );
    }

    /**
     * Check if the entity is in a specific state.
     */
    public function isInState(EntityInterface $entity, string $state): bool
    {
        return $this->getCurrentState($entity) === $state;
    }

    /**
     * Check if the entity is in a final state.
     */
    public function isFinal(EntityInterface $entity): bool
    {
        $currentState = $this->getCurrentState($entity);
        $stateObj = $this->getWorkflowDefinition()->getState($currentState);

        return $stateObj->isFinal();
    }

    /**
     * Check if the current state has a specific flag.
     */
    public function hasFlag(EntityInterface $entity, string $flag): bool
    {
        $currentState = $this->getCurrentState($entity);
        $stateObj = $this->getWorkflowDefinition()->getState($currentState);

        return $stateObj->hasFlag($flag);
    }

    /**
     * Get the workflow registry.
     *
     * @throws \Workflow\Exception\WorkflowException
     * @throws \Workflow\Exception\WorkflowException
     */
    protected function getRegistry(): WorkflowRegistry
    {
        $registry = $this->getConfig('registry');
        if ($registry === null) {
            throw new WorkflowException('WorkflowBehavior requires a registry instance');
        }

        return $registry;
    }
}
