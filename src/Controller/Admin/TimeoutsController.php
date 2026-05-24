<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use Cake\Http\Response;
use Cake\I18n\DateTime;
use RuntimeException;
use Throwable;
use Workflow\Service\TimeoutScheduler;
use Workflow\Service\TransitionLogger;
use Workflow\Service\WorkflowRegistry;

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
        if ($this->workflowRegistry instanceof WorkflowRegistry) {
            $workflowNames = $this->workflowRegistry->getWorkflowNames();
        }

        $this->set(['timeouts' => $timeouts, 'workflow' => $workflow, 'status' => $status, 'workflowNames' => $workflowNames]);
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
     * Cancel multiple pending timeouts in one request.
     */
    public function bulkCancel(): ?Response
    {
        $this->request->allowMethod(['post']);

        /** @var \Workflow\Model\Table\WorkflowTimeoutsTable $timeoutsTable */
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $timeoutIds = array_map('intval', (array)$this->request->getData('timeout_ids', []));
        $timeoutIds = array_values(array_filter($timeoutIds));

        if (!$timeoutIds) {
            $this->Flash->error('Select at least one timeout to cancel.');

            return $this->redirect(['action' => 'index']);
        }

        $cancelled = 0;
        $skipped = 0;

        /** @var array<\Workflow\Model\Entity\WorkflowTimeout> $timeouts */
        $timeouts = $timeoutsTable->find()->where(['id IN' => $timeoutIds])->all()->toArray();
        foreach ($timeouts as $timeout) {
            if ($timeout->processed) {
                $skipped++;

                continue;
            }

            $timeout->processed = true;
            if ($timeoutsTable->save($timeout)) {
                $cancelled++;
            } else {
                $skipped++;
            }
        }

        if ($cancelled > 0) {
            $this->Flash->success(sprintf('Cancelled %d timeout(s).', $cancelled));
        }
        if ($skipped > 0) {
            $this->Flash->warning(sprintf('Skipped %d timeout(s).', $skipped));
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
            'model' => $originalTimeout->model,
            'foreign_key' => $originalTimeout->foreign_key,
            'transition_name' => $originalTimeout->transition_name,
            'current_state' => $originalTimeout->current_state,
            'due_at' => DateTime::now(),
            'processed' => false,
        ]);

        if ($timeoutsTable->save($newTimeout)) {
            $this->Flash->success(sprintf(
                'Timeout retried. New timeout #%d created for entity #%s.',
                $newTimeout->get('id'),
                $originalTimeout->foreign_key,
            ));
        } else {
            $this->Flash->error('Could not create retry timeout.');
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Execute a single timeout immediately (manual trigger).
     *
     * @throws \RuntimeException
     */
    public function execute(int $id): ?Response
    {
        $this->request->allowMethod(['post']);

        if (!$this->workflowRegistry instanceof WorkflowRegistry) {
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

        $execution = $this->executeTimeoutRecord($timeoutsTable, $timeout);
        if ($execution['status'] === 'success') {
            $this->Flash->success(sprintf(
                'Timeout executed. Entity #%s transitioned to "%s".',
                $timeout->foreign_key,
                $execution['toState'] ?? 'unknown',
            ));
        } elseif ($execution['status'] === 'stale') {
            $this->Flash->warning(sprintf(
                'Entity state changed from "%s" to "%s". Timeout marked as processed.',
                $timeout->current_state,
                $execution['actualState'] ?? 'unknown',
            ));
        } elseif ($execution['status'] === 'blocked') {
            $blockedBy = $execution['blockedBy'] ?? ['unknown' => 'Transaction failed'];
            $this->Flash->warning('Transition blocked: ' . json_encode($blockedBy));
        } else {
            $this->Flash->error('Error executing timeout: ' . ($execution['message'] ?? 'Unknown error'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Execute multiple selected timeouts.
     */
    public function bulkExecute(): ?Response
    {
        $this->request->allowMethod(['post']);

        /** @var \Workflow\Model\Table\WorkflowTimeoutsTable $timeoutsTable */
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $timeoutIds = array_map('intval', (array)$this->request->getData('timeout_ids', []));
        $timeoutIds = array_values(array_filter($timeoutIds));

        if (!$timeoutIds) {
            $this->Flash->error('Select at least one timeout to execute.');

            return $this->redirect(['action' => 'index']);
        }

        $summary = [
            'success' => 0,
            'blocked' => 0,
            'stale' => 0,
            'error' => 0,
        ];

        /** @var array<\Workflow\Model\Entity\WorkflowTimeout> $timeouts */
        $timeouts = $timeoutsTable->find()->where(['id IN' => $timeoutIds])->all()->toArray();
        foreach ($timeouts as $timeout) {
            if ($timeout->processed) {
                $summary['error']++;

                continue;
            }

            $execution = $this->executeTimeoutRecord($timeoutsTable, $timeout);
            $summary[$execution['status']]++;
        }

        $this->flashTimeoutExecutionSummary($summary, count($timeouts));

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Execute every timeout that is already due.
     */
    public function executeDue(): ?Response
    {
        $this->request->allowMethod(['post']);

        /** @var \Workflow\Model\Table\WorkflowTimeoutsTable $timeoutsTable */
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        /** @var array<\Workflow\Model\Entity\WorkflowTimeout> $timeouts */
        $timeouts = $timeoutsTable->find()
            ->where([
                'processed' => false,
                'due_at <=' => DateTime::now(),
            ])
            ->orderBy(['due_at' => 'ASC'])
            ->all()
            ->toArray();

        if (!$timeouts) {
            $this->Flash->warning('No due timeouts found.');

            return $this->redirect(['action' => 'index']);
        }

        $summary = [
            'success' => 0,
            'blocked' => 0,
            'stale' => 0,
            'error' => 0,
        ];

        foreach ($timeouts as $timeout) {
            $execution = $this->executeTimeoutRecord($timeoutsTable, $timeout);
            $summary[$execution['status']]++;
        }

        $this->flashTimeoutExecutionSummary($summary, count($timeouts));

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
            $model = $this->fetchTable($timeout->model);
            $entity = $model->get($timeout->foreign_key);

            if ($this->workflowRegistry instanceof WorkflowRegistry) {
                $definition = $this->workflowRegistry->getWorkflow($timeout->workflow_name);
                $currentState = $entity->get($definition->getField());
                $stateMatches = $currentState === $timeout->current_state;
            }
        } catch (Throwable) {
            // Entity might not exist
        }

        $this->set(['timeout' => $timeout, 'entity' => $entity, 'currentState' => $currentState, 'stateMatches' => $stateMatches]);
    }

    /**
     * Execute a timeout record and return a normalized result payload.
     *
     * @param \Workflow\Model\Table\WorkflowTimeoutsTable $timeoutsTable
     * @param \Workflow\Model\Entity\WorkflowTimeout $timeout
     *
     * @return array{status: 'success'|'blocked'|'stale'|'error', toState?: string|null, actualState?: string|null, blockedBy?: array<string, mixed>, message?: string}
     */
    protected function executeTimeoutRecord($timeoutsTable, $timeout): array
    {
        if (!$this->workflowRegistry instanceof WorkflowRegistry) {
            return [
                'status' => 'error',
                'message' => 'Workflow registry not configured',
            ];
        }

        try {
            $definition = $this->workflowRegistry->getWorkflow($timeout->workflow_name);
            $field = $definition->getField();

            $model = $this->fetchTable($timeout->model);
            $entity = $model->get($timeout->foreign_key);

            if ($entity->get($field) !== $timeout->current_state) {
                $timeout->processed = true;
                $timeoutsTable->save($timeout);

                return [
                    'status' => 'stale',
                    'actualState' => (string)$entity->get($field),
                ];
            }

            $engine = $this->workflowRegistry->getEngine($timeout->workflow_name);
            $connection = $model->getConnection();
            $userId = $this->getCurrentUserId();
            $context = [
                'triggered_by' => 'admin_manual',
                'timeout_id' => $timeout->id,
                'admin_action' => true,
                'user_id' => $userId,
                'client_ip' => $this->request->clientIp(),
            ];
            $result = null;

            $success = $connection->transactional(function () use (
                $engine,
                $definition,
                $entity,
                $timeout,
                $model,
                $timeoutsTable,
                $context,
                $field,
                &$result,
            ): bool {
                $result = $engine->apply($definition, $entity, $timeout->transition_name, $context);

                if (!$result->isSuccess()) {
                    return false;
                }

                $model->saveOrFail($entity);

                $logger = new TransitionLogger();
                $logger->log(
                    $timeout->workflow_name,
                    $timeout->model,
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
                    $timeout->model,
                    $entity,
                    $definition->getState((string)$entity->get($field)),
                );

                return true;
            });

            if ($success) {
                return [
                    'status' => 'success',
                    'toState' => $result?->getToState(),
                ];
            }

            return [
                'status' => 'blocked',
                'blockedBy' => $result?->getBlockedBy() ?? ['unknown' => 'Transaction failed'],
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Flash a compact bulk-execution summary.
     *
     * @param array{success:int, blocked:int, stale:int, error:int} $summary
     * @param int $total
     */
    protected function flashTimeoutExecutionSummary(array $summary, int $total): void
    {
        if ($summary['success'] > 0) {
            $this->Flash->success(sprintf(
                'Processed %d timeout(s): %d executed.',
                $total,
                $summary['success'],
            ));
        }
        if ($summary['blocked'] > 0 || $summary['stale'] > 0 || $summary['error'] > 0) {
            $this->Flash->warning(sprintf(
                'Skipped %d timeout(s): %d blocked, %d stale, %d errors.',
                $summary['blocked'] + $summary['stale'] + $summary['error'],
                $summary['blocked'],
                $summary['stale'],
                $summary['error'],
            ));
        }
    }
}
