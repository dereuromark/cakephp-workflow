<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use Cake\Core\Configure;
use RuntimeException;
use Workflow\Service\WorkflowRegistry;

class WorkflowsController extends WorkflowAppController
{
    private WorkflowRegistry $registry;

    public function initialize(): void
    {
        parent::initialize();

        $registry = Configure::read('Workflow.registry');
        if (!$registry instanceof WorkflowRegistry) {
            throw new RuntimeException('Workflow registry not configured');
        }
        $this->registry = $registry;
    }

    /**
     * List all workflows.
     */
    public function index(): void
    {
        $workflowNames = $this->registry->getWorkflowNames();

        $workflows = [];
        foreach ($workflowNames as $name) {
            $definition = $this->registry->getWorkflow($name);
            $workflows[] = [
                'name' => $name,
                'definition' => $definition,
                'stateCount' => count($definition->getStates()),
                'transitionCount' => count($definition->getTransitions()),
            ];
        }

        $this->set(compact('workflows'));
    }

    /**
     * View a specific workflow.
     */
    public function view(string $name): void
    {
        $definition = $this->registry->getWorkflow($name);

        // Get recent transitions for this workflow
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
        $recentTransitions = $transitionsTable->find()
            ->where(['workflow_name' => $name])
            ->orderBy(['created' => 'DESC'])
            ->limit(20)
            ->toArray();

        // Get pending timeouts
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $pendingTimeouts = $timeoutsTable->find()
            ->where([
                'workflow_name' => $name,
                'processed' => false,
            ])
            ->orderBy(['due_at' => 'ASC'])
            ->limit(10)
            ->toArray();

        $this->set(compact('definition', 'recentTransitions', 'pendingTimeouts'));
    }
}
