<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use Cake\Http\Response;

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
}
