<?php

declare(strict_types=1);

namespace Workflow\Service;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventManager;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\EngineInterface;
use Workflow\Engine\StateMachineEngine;
use Workflow\Exception\WorkflowException;
use Workflow\Loader\LoaderInterface;
use Workflow\Workflow;

class WorkflowRegistry
{
    /**
     * @var array<string, \Workflow\Engine\EngineInterface>
     */
    private array $engines = [];

    public function __construct(
        private LoaderInterface $loader,
        private EventManager $eventManager,
        private bool $strictMode = false,
        private int $maxAutomaticTransitions = 10,
    ) {
    }

    public function hasWorkflow(string $name): bool
    {
        return $this->loader->supports($name);
    }

    /**
     * Get a workflow definition by name.
     *
     * @param string $name
     *
     * @throws \Workflow\Exception\WorkflowException When workflow is not found
     *
     * @return \Workflow\Engine\Definition\Definition
     */
    public function getWorkflow(string $name): Definition
    {
        if (!$this->loader->supports($name)) {
            throw new WorkflowException("Workflow '{$name}' not found");
        }

        return $this->loader->load($name);
    }

    /**
     * @return array<string>
     */
    public function getWorkflowNames(): array
    {
        return $this->loader->getWorkflowNames();
    }

    /**
     * Get or create an engine for the workflow.
     */
    public function getEngine(string $workflowName): EngineInterface
    {
        if (!isset($this->engines[$workflowName])) {
            $this->engines[$workflowName] = new StateMachineEngine(
                $this->eventManager,
                strictMode: $this->strictMode,
                maxAutomaticTransitions: $this->maxAutomaticTransitions,
            );
        }

        return $this->engines[$workflowName];
    }

    /**
     * Register a custom engine for a workflow.
     */
    public function setEngine(string $workflowName, EngineInterface $engine): void
    {
        $this->engines[$workflowName] = $engine;
    }

    /**
     * Get a Workflow object for an entity.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity
     * @param string|null $workflowName Optional workflow name (auto-detected from entity source if null)
     *
     * @return \Workflow\Workflow
     */
    public function get(EntityInterface $entity, ?string $workflowName = null): Workflow
    {
        if ($workflowName === null) {
            $workflowName = $this->detectWorkflow($entity);
        }

        $definition = $this->getWorkflow($workflowName);
        $engine = $this->getEngine($workflowName);

        return new Workflow($definition, $entity, $engine);
    }

    /**
     * Detect workflow name from entity's source table.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     *
     * @throws \Workflow\Exception\WorkflowException When no matching workflow found
     *
     * @return string Workflow name
     */
    private function detectWorkflow(EntityInterface $entity): string
    {
        $source = $entity->getSource();
        if (!$source) {
            throw new WorkflowException('Entity has no source table set. Specify workflow name explicitly.');
        }

        foreach ($this->getWorkflowNames() as $name) {
            $definition = $this->getWorkflow($name);
            if ($definition->getTable() === $source) {
                return $name;
            }
        }

        throw new WorkflowException("No workflow found for entity from table '{$source}'");
    }
}
