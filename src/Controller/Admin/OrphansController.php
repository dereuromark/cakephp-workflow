<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use Cake\Http\Response;
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

    /**
     * Fix an orphaned entity by setting it to a valid state.
     *
     * @param string $workflow Workflow name
     * @param string $entityId Entity ID to fix
     */
    public function fix(string $workflow, string $entityId): ?Response
    {
        $this->request->allowMethod(['get', 'post']);

        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $definition = $this->workflowRegistry->getWorkflow($workflow);
        $tableName = $definition->getTable();
        $field = $definition->getField();

        $table = $this->fetchTable($tableName);
        $entity = $table->get($entityId);

        $currentState = $entity->get($field);

        // Get valid states for the dropdown
        $validStates = [];
        foreach ($definition->getStates() as $state) {
            $validStates[$state->getName()] = $state->getLabel() ?: $state->getName();
        }

        if ($this->request->is('post')) {
            $newState = $this->request->getData('new_state');
            $reason = $this->request->getData('reason');

            if (!$newState || !isset($validStates[$newState])) {
                $this->Flash->error('Please select a valid state.');

                return null;
            }

            // Directly update the state field (bypassing workflow validation)
            $entity->set($field, $newState);
            $entity->setDirty($field, true);

            // Temporarily disable workflow validation if behavior is attached
            if ($table->hasBehavior('Workflow')) {
                $table->behaviors()->get('Workflow')->setConfig('validateOnSave', false);
            }

            try {
                if ($table->save($entity)) {
                    // Log this fix as an admin action
                    $this->logOrphanFix($workflow, $tableName, $entityId, $currentState, $newState, $reason);

                    $this->Flash->success(sprintf(
                        'Entity #%s state changed from "%s" to "%s".',
                        $entityId,
                        $currentState ?? 'NULL',
                        $newState,
                    ));

                    return $this->redirect(['action' => 'index', '?' => ['workflow' => $workflow]]);
                }

                $this->Flash->error('Could not save entity.');
            } finally {
                // Re-enable validation
                if ($table->hasBehavior('Workflow')) {
                    $table->behaviors()->get('Workflow')->setConfig('validateOnSave', true);
                }
            }
        }

        $this->set(compact('workflow', 'definition', 'entity', 'entityId', 'currentState', 'validStates', 'field'));

        return null;
    }

    /**
     * Bulk fix multiple orphans to a specific state.
     *
     * @param string $workflow Workflow name
     */
    public function bulkFix(string $workflow): ?Response
    {
        $this->request->allowMethod(['post']);

        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $definition = $this->workflowRegistry->getWorkflow($workflow);
        $tableName = $definition->getTable();
        $field = $definition->getField();

        $entityIds = $this->request->getData('entity_ids', []);
        $newState = $this->request->getData('new_state');
        $reason = $this->request->getData('reason');

        if (empty($entityIds) || !$newState) {
            $this->Flash->error('Please select entities and a target state.');

            return $this->redirect(['action' => 'index', '?' => ['workflow' => $workflow]]);
        }

        // Validate the new state exists
        $validStateNames = array_map(fn ($s) => $s->getName(), $definition->getStates());
        if (!in_array($newState, $validStateNames, true)) {
            $this->Flash->error('Invalid target state.');

            return $this->redirect(['action' => 'index', '?' => ['workflow' => $workflow]]);
        }

        $table = $this->fetchTable($tableName);

        // Temporarily disable workflow validation
        if ($table->hasBehavior('Workflow')) {
            $table->behaviors()->get('Workflow')->setConfig('validateOnSave', false);
        }

        $fixed = 0;
        $failed = 0;

        try {
            foreach ($entityIds as $entityId) {
                try {
                    $entity = $table->get($entityId);
                    $oldState = $entity->get($field);
                    $entity->set($field, $newState);

                    if ($table->save($entity)) {
                        $this->logOrphanFix($workflow, $tableName, (string)$entityId, $oldState, $newState, $reason);
                        $fixed++;
                    } else {
                        $failed++;
                    }
                } catch (Throwable) {
                    $failed++;
                }
            }
        } finally {
            // Re-enable validation
            if ($table->hasBehavior('Workflow')) {
                $table->behaviors()->get('Workflow')->setConfig('validateOnSave', true);
            }
        }

        if ($fixed > 0) {
            $this->Flash->success(sprintf('Fixed %d orphaned entities.', $fixed));
        }
        if ($failed > 0) {
            $this->Flash->warning(sprintf('Failed to fix %d entities.', $failed));
        }

        return $this->redirect(['action' => 'index', '?' => ['workflow' => $workflow]]);
    }

    /**
     * Log an orphan fix action.
     *
     * @param string $workflow
     * @param string $tableName
     * @param string $entityId
     * @param string|null $oldState
     * @param string $newState
     * @param string|null $reason
     */
    private function logOrphanFix(
        string $workflow,
        string $tableName,
        string $entityId,
        ?string $oldState,
        string $newState,
        ?string $reason,
    ): void {
        try {
            $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
            $transition = $transitionsTable->newEntity([
                'workflow_name' => $workflow,
                'entity_table' => $tableName,
                'entity_id' => $entityId,
                'transition_name' => '_admin_fix',
                'from_state' => $oldState ?? '_orphaned',
                'to_state' => $newState,
                'context' => json_encode([
                    'type' => 'orphan_fix',
                    'reason' => $reason,
                    'admin_action' => true,
                ]),
            ]);
            $transitionsTable->save($transition);
        } catch (Throwable) {
            // Logging failure should not break the fix operation
        }
    }
}
