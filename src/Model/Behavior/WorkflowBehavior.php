<?php

declare(strict_types=1);

namespace Workflow\Model\Behavior;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\EngineInterface;
use Workflow\Engine\TransitionResult;
use Workflow\Exception\WorkflowException;
use Workflow\Service\TransitionLogger;
use Workflow\Service\WorkflowRegistry;

class WorkflowBehavior extends Behavior
{
    /**
     * Marker to track entities that have gone through applyTransition
     *
     * @var string
     */
    private const TRANSITION_MARKER = '_workflow_transition_applied';

    protected array $_defaultConfig = [
        'workflow' => null,
        'registry' => null,
        'field' => null,
        'validateOnSave' => true,
        'autoSave' => false,
        'autoLog' => false,
        'entityTable' => null, // Auto-detected if not set
    ];

    private ?Definition $definition = null;

    private ?EngineInterface $engine = null;

    private ?TransitionLogger $logger = null;

    public function initialize(array $config): void
    {
        parent::initialize($config);

        if ($this->getConfig('workflow') === null) {
            throw new WorkflowException('WorkflowBehavior requires a workflow name');
        }

        // Auto-detect entity table name if not set
        if ($this->getConfig('entityTable') === null) {
            $this->setConfig('entityTable', $this->_table->getRegistryAlias());
        }
    }

    /**
     * Validate state changes on save.
     *
     * Prevents direct state field modifications that bypass the workflow system.
     *
     * @param \Cake\Event\EventInterface $event
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \ArrayObject $options
     *
     * @throws \Workflow\Exception\WorkflowException When state is changed directly without using applyTransition
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (!$this->getConfig('validateOnSave')) {
            return;
        }

        $field = $this->getWorkflowDefinition()->getField();

        // Check if state field was changed
        if (!$entity->isDirty($field)) {
            return;
        }

        // Check if this change came through applyTransition
        if ($entity->get(self::TRANSITION_MARKER) === true) {
            // Clear the marker
            $entity->unset(self::TRANSITION_MARKER);

            return;
        }

        // For new entities, allow setting initial state
        if ($entity->isNew()) {
            $initialState = $this->getWorkflowDefinition()->getInitialState()->getName();
            $newState = $entity->get($field);

            // Allow setting to initial state or leaving empty (will default to initial)
            if ($newState === null || $newState === '' || $newState === $initialState) {
                return;
            }

            throw new WorkflowException(
                "Cannot set initial state to '{$newState}'. New entities must start in the initial state '{$initialState}'.",
            );
        }

        // For existing entities, direct state changes are not allowed
        $originalState = $entity->getOriginal($field);
        $newState = $entity->get($field);

        throw new WorkflowException(
            "Direct state changes are not allowed. Use applyTransition() to change state from '{$originalState}' to '{$newState}'.",
        );
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
     * When autoSave is enabled, the entity is saved after a successful transition.
     * When autoLog is enabled, the transition is logged automatically.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context Context data. Use 'reason' key for transition reason.
     */
    public function applyTransition(EntityInterface $entity, string $transition, array $context = []): TransitionResult
    {
        $result = $this->getWorkflowEngine()->apply(
            $this->getWorkflowDefinition(),
            $entity,
            $transition,
            $context,
        );

        if ($result->isSuccess()) {
            // Mark the entity so beforeSave knows this change came through the workflow
            $entity->set(self::TRANSITION_MARKER, true);

            // Auto-save if enabled
            if ($this->getConfig('autoSave')) {
                $this->_table->saveOrFail($entity);
            }

            // Auto-log if enabled
            if ($this->getConfig('autoLog')) {
                $this->logTransition($entity, $result, $transition, $context);
            }
        }

        return $result;
    }

    /**
     * Log a transition.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \Workflow\Engine\TransitionResult $result
     * @param string $transition
     * @param array<string, mixed> $context
     */
    protected function logTransition(
        EntityInterface $entity,
        TransitionResult $result,
        string $transition,
        array $context,
    ): void {
        $definition = $this->getWorkflowDefinition();
        $this->getLogger()->log(
            $this->getConfig('workflow'),
            $this->getConfig('entityTable'),
            $entity,
            $result,
            $transition,
            $context,
            (string)$definition->getVersion(),
        );
    }

    /**
     * Get the transition logger.
     */
    protected function getLogger(): TransitionLogger
    {
        if ($this->logger === null) {
            $this->logger = new TransitionLogger();
        }

        return $this->logger;
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
