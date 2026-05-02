<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Exception\ForbiddenException;
use Cake\Log\Log;
use Closure;
use Throwable;
use Workflow\Service\WorkflowRegistry;
use Workflow\Service\WorkflowRegistryLocator;

/**
 * Base controller for the Workflow admin backend.
 *
 * The admin UI can rewrite workflow definitions and trigger transitions, so
 * the default policy is **deny**. The host application MUST configure
 * `Workflow.adminAccess` as a Closure that receives the current request and
 * returns literal `true` to grant access; anything else (unset, non-Closure,
 * returns false, returns a truthy non-bool, or throws) yields a 403.
 *
 * ```php
 * Configure::write('Workflow.adminAccess', function (\Cake\Http\ServerRequest $request): bool {
 *     $identity = $request->getAttribute('identity');
 *     return $identity !== null && in_array('admin', (array)$identity->roles, true);
 * });
 * ```
 *
 * Because this plugin's controllers extend the bare `Cake\Controller\Controller`
 * (not the host app's `AppController`), per-controller auth wired through the
 * host AppController would never run anyway. The explicit gate makes that
 * deliberate rather than implicit.
 */
class WorkflowAppController extends Controller
{
    protected ?WorkflowRegistry $workflowRegistry = null;

    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
        $this->viewBuilder()->setLayout('Workflow.workflow');

        $registry = WorkflowRegistryLocator::get() ?? Configure::read('Workflow.registry');
        if ($registry instanceof WorkflowRegistry) {
            $this->workflowRegistry = $registry;
        }
    }

    /**
     * Default-deny access gate.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event
     *
     * @throws \Cake\Http\Exception\ForbiddenException When access is denied or unconfigured.
     *
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        // Coexist with cakephp/authorization: this gate IS the authorization
        // decision for the workflow admin, so silence the policy check.
        if ($this->components()->has('Authorization') && method_exists($this->components()->get('Authorization'), 'skipAuthorization')) {
            $this->components()->get('Authorization')->skipAuthorization();
        }

        $gate = Configure::read('Workflow.adminAccess');
        if (!($gate instanceof Closure)) {
            throw new ForbiddenException(__d('workflow', 'Workflow admin backend is not configured. Set Workflow.adminAccess to a Closure that returns true for permitted callers.'));
        }

        try {
            $allowed = $gate($this->request) === true;
        } catch (ForbiddenException $e) {
            // Caller explicitly chose the 403 path — respect it.
            throw $e;
        } catch (Throwable $e) {
            Log::warning(sprintf(
                'Workflow.adminAccess threw %s: %s',
                $e::class,
                $e->getMessage(),
            ));

            throw new ForbiddenException(__d('workflow', 'Workflow admin access denied.'));
        }

        if (!$allowed) {
            throw new ForbiddenException(__d('workflow', 'Workflow admin access denied.'));
        }
    }

    public function beforeRender(EventInterface $event): void
    {
        parent::beforeRender($event);

        $this->viewBuilder()->addHelpers(['Workflow.Workflow']);

        // Pass sidebar data to all views
        $this->set('workflowStats', $this->getWorkflowStats());
        $this->set('pendingTimeoutsCount', $this->getPendingTimeoutsCount());
        $this->set('orphanCount', $this->getOrphanCount());
    }

    /**
     * Resolve the current operator id from the request identity.
     *
     * Coerces the identity primary key to a string suitable for the
     * `workflow_transitions.user_id` column. Returns null when no identity
     * is attached or when the id cannot be coerced.
     *
     * @return string|null
     */
    protected function getCurrentUserId(): ?string
    {
        $identity = $this->request->getAttribute('identity');
        if ($identity === null) {
            return null;
        }

        try {
            $id = $identity->getIdentifier();
        } catch (Throwable) {
            return null;
        }

        if ($id === null) {
            return null;
        }

        if (is_int($id) || is_string($id)) {
            $value = (string)$id;

            return $value !== '' ? $value : null;
        }

        return null;
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

    /**
     * Get orphan count for sidebar badge.
     *
     * Orphans are items whose current state doesn't match any defined state in the workflow.
     */
    protected function getOrphanCount(): int
    {
        if ($this->workflowRegistry === null) {
            return 0;
        }

        $totalOrphans = 0;
        $workflowNames = $this->workflowRegistry->getWorkflowNames();

        foreach ($workflowNames as $name) {
            $definition = $this->workflowRegistry->getWorkflow($name);
            $tableName = $definition->getTable();
            $field = $definition->getField();

            // Get valid state names
            $validStates = array_map(
                fn ($s) => $s->getName(),
                $definition->getStates(),
            );

            if (!$validStates) {
                continue;
            }

            try {
                $table = $this->fetchTable($tableName);
                $totalOrphans += $table->find()
                    ->where([$field . ' NOT IN' => $validStates])
                    ->count();
            } catch (Throwable) {
                // Table might not exist
            }
        }

        return $totalOrphans;
    }
}
