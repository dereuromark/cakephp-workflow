<?php
declare(strict_types=1);

namespace Workflow\Service;

use Cake\Event\EventManager;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\EngineInterface;
use Workflow\Engine\StateMachineEngine;
use Workflow\Exception\WorkflowException;
use Workflow\Loader\LoaderInterface;

class WorkflowRegistry
{
    /** @var array<string, EngineInterface> */
    private array $engines = [];

    public function __construct(
        private LoaderInterface $loader,
        private EventManager $eventManager,
    ) {
    }

    public function hasWorkflow(string $name): bool
    {
        return $this->loader->supports($name);
    }

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
            $this->engines[$workflowName] = new StateMachineEngine($this->eventManager);
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
}
