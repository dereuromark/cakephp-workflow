<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use Cake\Database\Expression\QueryExpression;
use Cake\Http\Exception\NotFoundException;
use DateTimeImmutable;
use Workflow\Service\WorkflowRegistry;

class TransitionsController extends WorkflowAppController
{
    /**
     * List all workflow transitions.
     */
    public function index(): void
    {
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');

        $query = $transitionsTable->find()
            ->orderBy(['id' => 'DESC']);

        // Filter by workflow
        $workflow = $this->request->getQuery('workflow');
        if ($workflow) {
            $query->where(['workflow_name' => $workflow]);
        }

        // Filter by entity
        $foreignKey = $this->request->getQuery('foreign_key');
        if ($foreignKey) {
            $query->where(['foreign_key' => $foreignKey]);
        }

        $status = $this->request->getQuery('status');
        if ($status) {
            $query->where(['status' => $status]);
        }

        $userId = $this->request->getQuery('user_id');
        if ($userId) {
            $query->where(['user_id' => $userId]);
        }

        $adminAction = $this->request->getQuery('admin_action');
        if ($adminAction === 'yes') {
            $query->where(['context LIKE \'%"admin_action":true%\'']);
        } elseif ($adminAction === 'no') {
            $query->where(function (QueryExpression $exp) {
                return $exp->or([
                    $exp->isNull('context'),
                    'context NOT LIKE \'%"admin_action":true%\'',
                ]);
            });
        }

        $createdFrom = $this->request->getQuery('created_from');
        if (is_string($createdFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $createdFrom)) {
            $query->where(['created >=' => $createdFrom . ' 00:00:00']);
        }

        $createdTo = $this->request->getQuery('created_to');
        if (is_string($createdTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $createdTo)) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $createdTo);
            if ($date !== false) {
                $query->where(['created <' => $date->modify('+1 day')->format('Y-m-d') . ' 00:00:00']);
            }
        }

        $transitions = $this->paginate($query, [
            'limit' => 50,
        ]);

        // Get workflow names for filter dropdown
        $workflowNames = [];
        if ($this->workflowRegistry instanceof WorkflowRegistry) {
            $workflowNames = $this->workflowRegistry->getWorkflowNames();
        }

        $statusOptions = [
            '' => 'All Statuses',
            'success' => 'Success',
            'blocked' => 'Blocked',
            'locked' => 'Locked',
            'error' => 'Error',
        ];
        $adminActionOptions = [
            '' => 'All Origins',
            'yes' => 'Admin Actions',
            'no' => 'Automated / Runtime',
        ];

        $this->set(compact(
            'transitions',
            'workflow',
            'foreignKey',
            'workflowNames',
            'status',
            'userId',
            'adminAction',
            'createdFrom',
            'createdTo',
            'statusOptions',
            'adminActionOptions',
        ));
    }

    /**
     * View details for a single transition.
     *
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function view(int $id): void
    {
        /** @var \Workflow\Model\Table\WorkflowTransitionsTable $transitionsTable */
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
        /** @var \Workflow\Model\Entity\WorkflowTransition|null $transition */
        $transition = $transitionsTable->find()->where(['id' => $id])->first();
        if ($transition === null) {
            throw new NotFoundException(sprintf('Transition #%d not found.', $id));
        }

        $this->set(compact('transition'));
    }
}
