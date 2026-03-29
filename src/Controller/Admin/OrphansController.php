<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use RuntimeException;
use Throwable;

class OrphansController extends WorkflowAppController
{
    /**
     * List all orphaned items across workflows.
     *
     * Orphans are items whose current state doesn't match any defined state in the workflow.
     */
    public function index(): void
    {
        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $workflowNames = $this->workflowRegistry->getWorkflowNames();

        // Filter by workflow if specified
        $selectedWorkflow = $this->request->getQuery('workflow');

        $orphans = [];
        $orphanCounts = [];

        foreach ($workflowNames as $name) {
            // Skip if filtering by workflow and this isn't it
            if ($selectedWorkflow && $selectedWorkflow !== $name) {
                continue;
            }

            $definition = $this->workflowRegistry->getWorkflow($name);
            $tableName = $definition->getTable();
            $field = $definition->getField();

            // Get valid state names
            $validStates = [];
            foreach ($definition->getStates() as $state) {
                $validStates[] = $state->getName();
            }

            if (!$validStates) {
                continue;
            }

            try {
                $table = $this->fetchTable($tableName);

                // Find items with states NOT in the valid states list
                /** @var array<\Cake\Datasource\EntityInterface> $orphanedItems */
                $orphanedItems = $table->find()
                    ->where([$field . ' NOT IN' => $validStates])
                    ->orderBy(['id' => 'DESC'])
                    ->limit(100)
                    ->toArray();

                $count = $table->find()
                    ->where([$field . ' NOT IN' => $validStates])
                    ->count();

                $orphanCounts[$name] = $count;

                foreach ($orphanedItems as $item) {
                    $orphans[] = [
                        'workflow' => $name,
                        'table' => $tableName,
                        'field' => $field,
                        'entity' => $item,
                        'current_state' => $item->get($field),
                        'valid_states' => $validStates,
                    ];
                }
            } catch (Throwable) {
                // Table might not exist
                $orphanCounts[$name] = 0;
            }
        }

        $totalOrphans = array_sum($orphanCounts);

        $this->set(compact('orphans', 'orphanCounts', 'totalOrphans', 'workflowNames', 'selectedWorkflow'));
    }
}
