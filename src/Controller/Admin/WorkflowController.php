<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use Cake\I18n\DateTime;
use RuntimeException;
use Throwable;

class WorkflowController extends WorkflowAppController
{
    /**
     * Dashboard with overview stats.
     */
    public function index(): void
    {
        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $workflowNames = $this->workflowRegistry->getWorkflowNames();

        // Calculate dashboard stats
        $totalActiveItems = 0;
        $workflows = [];

        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');

        // Transitions today
        $transitionsToday = $transitionsTable->find()
            ->where(['created >=' => DateTime::now()->startOfDay()])
            ->count();

        // Build workflow data
        foreach ($workflowNames as $name) {
            $definition = $this->workflowRegistry->getWorkflow($name);
            $tableName = $definition->getTable();
            $field = $definition->getField();

            // Count items by state
            $stateCounts = [];
            try {
                $table = $this->fetchTable($tableName);
                $states = $definition->getStates();

                foreach ($states as $state) {
                    $stateName = $state->getName();
                    if ($state->isFinal()) {
                        $stateCounts[$stateName] = null; // Final states show as "-"
                    } else {
                        $count = $table->find()
                            ->where([$field => $stateName])
                            ->count();
                        $stateCounts[$stateName] = $count;
                        $totalActiveItems += $count;
                    }
                }
            } catch (Throwable) {
                // Table might not exist, skip
            }

            $workflows[] = [
                'name' => $name,
                'definition' => $definition,
                'stateCounts' => $stateCounts,
            ];
        }

        // Get pending timeouts
        $pendingTimeouts = $timeoutsTable->find()
            ->where(['processed' => false])
            ->orderBy(['due_at' => 'ASC'])
            ->limit(5)
            ->toArray();

        // Get recent transitions
        $recentTransitions = $transitionsTable->find()
            ->orderBy(['created' => 'DESC'])
            ->limit(10)
            ->toArray();

        $this->set(compact(
            'workflows',
            'totalActiveItems',
            'transitionsToday',
            'pendingTimeouts',
            'recentTransitions',
        ));
    }
}
