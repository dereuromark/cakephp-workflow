<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Throwable;
use Workflow\Service\WorkflowRegistry;

class WorkflowAppController extends Controller
{
    protected ?WorkflowRegistry $workflowRegistry = null;

    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
        $this->viewBuilder()->setLayout('Workflow.workflow');

        $registry = Configure::read('Workflow.registry');
        if ($registry instanceof WorkflowRegistry) {
            $this->workflowRegistry = $registry;
        }
    }

    public function beforeRender(EventInterface $event): void
    {
        parent::beforeRender($event);

        $this->viewBuilder()->addHelpers(['Workflow.Workflow']);

        // Pass sidebar data to all views
        $this->set('workflowStats', $this->getWorkflowStats());
        $this->set('pendingTimeoutsCount', $this->getPendingTimeoutsCount());
    }

    /**
     * Get workflow stats for sidebar.
     *
     * @return array<string, array{name: string, count: int}>
     */
    protected function getWorkflowStats(): array
    {
        if ($this->workflowRegistry === null) {
            return [];
        }

        $stats = [];
        $workflowNames = $this->workflowRegistry->getWorkflowNames();

        foreach ($workflowNames as $name) {
            $definition = $this->workflowRegistry->getWorkflow($name);
            $tableName = $definition->getTable();
            $field = $definition->getField();

            // Count non-final state items
            try {
                $table = $this->fetchTable($tableName);
                $finalStates = array_map(
                    fn ($s) => $s->getName(),
                    $definition->getFinalStates(),
                );

                $query = $table->find();
                if ($finalStates) {
                    $query->whereNotInList($field, $finalStates);
                }
                $count = $query->count();

                $stats[$name] = [
                    'name' => $name,
                    'count' => $count,
                ];
            } catch (Throwable) {
                // Table might not exist, skip
                $stats[$name] = [
                    'name' => $name,
                    'count' => 0,
                ];
            }
        }

        return $stats;
    }

    /**
     * Get pending timeouts count for sidebar badge.
     */
    protected function getPendingTimeoutsCount(): int
    {
        try {
            $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');

            return $timeoutsTable->find()
                ->where(['processed' => false])
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
