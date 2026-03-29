<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

class TransitionsController extends WorkflowAppController
{
    /**
     * List all workflow transitions.
     */
    public function index(): void
    {
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');

        $query = $transitionsTable->find()
            ->orderBy(['created' => 'DESC']);

        // Filter by workflow
        $workflow = $this->request->getQuery('workflow');
        if ($workflow) {
            $query->where(['workflow_name' => $workflow]);
        }

        // Filter by entity
        $entityId = $this->request->getQuery('entity_id');
        if ($entityId) {
            $query->where(['entity_id' => $entityId]);
        }

        $transitions = $this->paginate($query, [
            'limit' => 50,
        ]);

        // Get workflow names for filter dropdown
        $workflowNames = [];
        if ($this->workflowRegistry !== null) {
            $workflowNames = $this->workflowRegistry->getWorkflowNames();
        }

        $this->set(compact('transitions', 'workflow', 'entityId', 'workflowNames'));
    }
}
