<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use Cake\Http\Response;
use Cake\I18n\DateTime;

class LocksController extends WorkflowAppController
{
    /**
     * List all workflow locks.
     */
    public function index(): void
    {
        $locksTable = $this->fetchTable('Workflow.WorkflowLocks');

        $query = $locksTable->find()
            ->orderBy(['id' => 'DESC']);

        // Filter by workflow
        $workflow = $this->request->getQuery('workflow');
        if ($workflow) {
            $query->where(['workflow_name' => $workflow]);
        }

        // Filter by status
        $status = $this->request->getQuery('status', 'active');
        $now = DateTime::now();
        if ($status === 'active') {
            $query->where(['expires_at >' => $now]);
        } elseif ($status === 'expired') {
            $query->where(['expires_at <=' => $now]);
        }

        $locks = $this->paginate($query, [
            'limit' => 50,
        ]);

        // Get workflow names for filter dropdown
        $workflowNames = [];
        if ($this->workflowRegistry !== null) {
            $workflowNames = $this->workflowRegistry->getWorkflowNames();
        }

        $this->set(compact('locks', 'workflow', 'status', 'workflowNames'));
    }

    /**
     * Release a lock.
     */
    public function release(int $id): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);

        $locksTable = $this->fetchTable('Workflow.WorkflowLocks');
        $lock = $locksTable->get($id);

        if ($locksTable->delete($lock)) {
            $this->Flash->success('Lock released successfully.');
        } else {
            $this->Flash->error('Could not release lock.');
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Cleanup expired locks.
     */
    public function cleanup(): ?Response
    {
        $this->request->allowMethod(['post']);

        $locksTable = $this->fetchTable('Workflow.WorkflowLocks');
        $deleted = $locksTable->deleteAll([
            'expires_at <=' => DateTime::now(),
        ]);

        $this->Flash->success(sprintf('%d expired lock(s) cleaned up.', $deleted));

        return $this->redirect(['action' => 'index']);
    }
}
