<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use Cake\Http\Response;
use Cake\I18n\DateTime;
use RuntimeException;
use Throwable;
use Workflow\Service\TimeoutScheduler;
use Workflow\Service\TransitionLogger;

class TimeoutsController extends WorkflowAppController
{
    /**
     * List all workflow timeouts.
     */
    public function index(): void
    {
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');

        $query = $timeoutsTable->find()
            ->orderBy(['due_at' => 'ASC']);

        // Filter by workflow
        $workflow = $this->request->getQuery('workflow');
        if ($workflow) {
            $query->where(['workflow_name' => $workflow]);
        }

        // Filter by processed status
        $status = $this->request->getQuery('status', 'pending');
        if ($status === 'pending') {
            $query->where(['processed' => false]);
        } elseif ($status === 'processed') {
            $query->where(['processed' => true]);
        }

        $timeouts = $this->paginate($query, [
            'limit' => 50,
        ]);

        // Get workflow names for filter dropdown
        $workflowNames = [];
        if ($this->workflowRegistry !== null) {
            $workflowNames = $this->workflowRegistry->getWorkflowNames();
        }

        $this->set(compact('timeouts', 'workflow', 'status', 'workflowNames'));
    }

    /**
     * Cancel a pending timeout.
     */
    public function cancel(int $id): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);

        /** @var \Workflow\Model\Table\WorkflowTimeoutsTable $timeoutsTable */
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        /** @var \Workflow\Model\Entity\WorkflowTimeout $timeout */
        $timeout = $timeoutsTable->get($id);

        if ($timeout->processed) {
            $this->Flash->error('This timeout has already been processed.');

            return $this->redirect(['action' => 'index']);
        }

        $timeout->processed = true;

        if ($timeoutsTable->save($timeout)) {
            $this->Flash->success('Timeout cancelled successfully.');
        } else {
            $this->Flash->error('Could not cancel timeout.');
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Retry a processed/failed timeout.
     *
     * Creates a new timeout entry with the same parameters, due immediately.
     */
    public function retry(int $id): ?Response
    {
        $this->request->allowMethod(['post']);

        /** @var \Workflow\Model\Table\WorkflowTimeoutsTable $timeoutsTable */
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        /** @var \Workflow\Model\Entity\WorkflowTimeout $originalTimeout */
        $originalTimeout = $timeoutsTable->get($id);

        // Create a new timeout entry based on the original
        $newTimeout = $timeoutsTable->newEntity([
            'workflow_name' => $originalTimeout->workflow_name,
            'entity_table' => $originalTimeout->entity_table,
            'entity_id' => $originalTimeout->entity_id,
            'transition_name' => $originalTimeout->transition_name,
            'current_state' => $originalTimeout->current_state,
            'due_at' => DateTime::now(),
            'processed' => false,
        ]);

        if ($timeoutsTable->save($newTimeout)) {
            $this->Flash->success(sprintf(
                'Timeout retried. New timeout #%d created for entity #%s.',
                $newTimeout->get('id'),
                $originalTimeout->entity_id,
            ));
        } else {
            $this->Flash->error('Could not create retry timeout.');
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Execute a single timeout immediately (manual trigger).
     */
    public function execute(int $id): ?Response
    {
        $this->request->allowMethod(['post']);

        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        /** @var \Workflow\Model\Table\WorkflowTimeoutsTable $timeoutsTable */
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        /** @var \Workflow\Model\Entity\WorkflowTimeout $timeout */
        $timeout = $timeoutsTable->get($id);

        if ($timeout->processed) {
            $this->Flash->error('This timeout has already been processed.');

            return $this->redirect(['action' => 'index']);
        }

        try {
            $definition = $this->workflowRegistry->getWorkflow($timeout->workflow_name);
            $field = $definition->getField();

            $entityTable = $this->fetchTable($timeout->entity_table);
            $entity = $entityTable->get($timeout->entity_id);

            if ($entity->get($field) !== $timeout->current_state) {
                $timeout->processed = true;
                $timeoutsTable->save($timeout);

                $this->Flash->warning(sprintf(
                    'Entity state changed from "%s" to "%s". Timeout marked as processed.',
                    $timeout->current_state,
                    $entity->get($field),
                ));

                return $this->redirect(['action' => 'index']);
            }

            $engine = $this->workflowRegistry->getEngine($timeout->workflow_name);
            $connection = $entityTable->getConnection();
            $context = [
                'triggered_by' => 'admin_manual',
                'timeout_id' => $timeout->id,
            ];
            $result = null;

            $success = $connection->transactional(function () use (
                $engine,
                $definition,
                $entity,
                $timeout,
                $entityTable,
                $timeoutsTable,
                $context,
                $field,
                &$result,
            ): bool {
                $result = $engine->apply($definition, $entity, $timeout->transition_name, $context);

                if (!$result->isSuccess()) {
                    return false;
                }

                $entityTable->saveOrFail($entity);

                $logger = new TransitionLogger();
                $logger->log(
                    $timeout->workflow_name,
                    $timeout->entity_table,
                    $entity,
                    $result,
                    $timeout->transition_name,
                    $context,
                    (string)$definition->getVersion(),
                );

                $timeout->processed = true;
                $timeoutsTable->saveOrFail($timeout);

                $scheduler = new TimeoutScheduler();
                $scheduler->syncStateTimeouts(
                    $timeout->workflow_name,
                    $timeout->entity_table,
                    $entity,
                    $definition->getState((string)$entity->get($field)),
                );

                return true;
            });

            if ($success) {
                $this->Flash->success(sprintf(
                    'Timeout executed. Entity #%s transitioned to "%s".',
                    $timeout->entity_id,
                    $result?->getToState() ?? 'unknown',
                ));
            } else {
                $blockedBy = $result !== null ? $result->getBlockedBy() : ['unknown' => 'Transaction failed'];
                $this->Flash->warning('Transition blocked: ' . json_encode($blockedBy));
            }
        } catch (Throwable $e) {
            $this->Flash->error('Error executing timeout: ' . $e->getMessage());
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * View details of a single timeout.
     */
    public function view(int $id): void
    {
        /** @var \Workflow\Model\Table\WorkflowTimeoutsTable $timeoutsTable */
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        /** @var \Workflow\Model\Entity\WorkflowTimeout $timeout */
        $timeout = $timeoutsTable->get($id);

        $entity = null;
        $currentState = null;
        $stateMatches = null;

        try {
            $entityTable = $this->fetchTable($timeout->entity_table);
            $entity = $entityTable->get($timeout->entity_id);

            if ($this->workflowRegistry !== null) {
                $definition = $this->workflowRegistry->getWorkflow($timeout->workflow_name);
                $currentState = $entity->get($definition->getField());
                $stateMatches = $currentState === $timeout->current_state;
            }
        } catch (Throwable) {
            // Entity might not exist
        }

        $this->set(compact('timeout', 'entity', 'currentState', 'stateMatches'));
    }
}
