<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use Cake\I18n\DateTime;
use RuntimeException;
use Throwable;

class WorkflowsController extends WorkflowAppController
{
    /**
     * List all workflows.
     *
     * @throws \RuntimeException
     */
    public function index(): void
    {
        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $workflowNames = $this->workflowRegistry->getWorkflowNames();

        $workflows = [];
        foreach ($workflowNames as $name) {
            $definition = $this->workflowRegistry->getWorkflow($name);
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
        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $definition = $this->workflowRegistry->getWorkflow($name);
        $tableName = $definition->getTable();
        $field = $definition->getField();

        // Count items by state
        $stateCounts = [];
        $totalActive = 0;
        try {
            $table = $this->fetchTable($tableName);
            foreach ($definition->getStates() as $state) {
                $stateName = $state->getName();
                $count = $table->find()
                    ->where([$field => $stateName])
                    ->count();
                $stateCounts[$stateName] = $count;
                if (!$state->isFinal()) {
                    $totalActive += $count;
                }
            }
        } catch (Throwable) {
            // Table might not exist
        }

        // Get recent transitions for this workflow
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
        $recentTransitions = $transitionsTable->find()
            ->where(['workflow_name' => $name])
            ->orderBy(['created' => 'DESC'])
            ->limit(20)
            ->toArray();

        // Transitions today
        $transitionsToday = $transitionsTable->find()
            ->where([
                'workflow_name' => $name,
                'created >=' => DateTime::now()->startOfDay(),
            ])
            ->count();

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

        $this->set(compact(
            'definition',
            'stateCounts',
            'totalActive',
            'recentTransitions',
            'transitionsToday',
            'pendingTimeouts',
        ));
    }
}
