<?php

declare(strict_types=1);

namespace Workflow\Model\Behavior;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Behavior;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query\SelectQuery;
use Closure;
use Throwable;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\EngineInterface;
use Workflow\Engine\TransitionResult;
use Workflow\Exception\WorkflowException;
use Workflow\Model\Entity\WorkflowLock;
use Workflow\Service\LockManager;
use Workflow\Service\TimeoutScheduler;
use Workflow\Service\TransitionLogger;
use Workflow\Service\WorkflowRegistry;
use Workflow\Service\WorkflowRegistryLocator;

class WorkflowBehavior extends Behavior
{
    use LocatorAwareTrait;

    /**
     * Marker to track entities that have gone through applyTransition
     *
     * @var string
     */
    private const TRANSITION_MARKER = '_workflow_transition_applied';

    protected array $_defaultConfig = [
        'workflow' => null,
        'registry' => null,
        'field' => null,
        'validateOnSave' => true,
        'autoSave' => false,
        'autoLog' => false,
        'logAllOutcomes' => true, // When true, also log blocked/error transitions for audit trail
        'model' => null, // Auto-detected if not set
        // Column stamped with the current time on every state change. Auto-applied
        // only when the table actually has this column; set to null/false to disable.
        'stateTimestampField' => 'state_changed_at',
        // Lock-free concurrency safety: persist the state change with a compare-and-set
        // (UPDATE ... WHERE state = :from). A losing writer gets a conflict result.
        // An alternative to the pessimistic lock table; takes precedence over useLocking.
        'useOptimisticLock' => false,
        'useLocking' => null, // null=auto-detect, true=force on, false=force off
        'useTimeouts' => null, // null=auto-detect, true=force on, false=force off
        'useTransaction' => true, // Wrap transition+save+log in database transaction
    ];

    private ?Definition $definition = null;

    private ?EngineInterface $engine = null;

    private ?TransitionLogger $logger = null;

    private ?LockManager $lockManager = null;

    private ?TimeoutScheduler $timeoutScheduler = null;

    private ?bool $lockTableExists = null;

    private ?bool $timeoutsTableExists = null;

    public function initialize(array $config): void
    {
        parent::initialize($config);

        if ($this->getConfig('workflow') === null) {
            throw new WorkflowException('WorkflowBehavior requires a workflow name');
        }

        // Auto-detect entity table name if not set
        if ($this->getConfig('model') === null) {
            $this->setConfig('model', $this->_table->getRegistryAlias());
        }
    }

    /**
     * Validate state changes on save.
     *
     * Prevents direct state field modifications that bypass the workflow system.
     *
     * @param \Cake\Event\EventInterface $event
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \ArrayObject $options
     *
     * @throws \Workflow\Exception\WorkflowException When state is changed directly without using applyTransition
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (!$this->getConfig('validateOnSave')) {
            return;
        }

        $field = $this->getWorkflowDefinition()->getField();

        // Check if state field was changed
        if (!$entity->isDirty($field)) {
            return;
        }

        // Check if this change came through applyTransition
        if ($entity->get(self::TRANSITION_MARKER) === true) {
            // Clear the marker
            $entity->unset(self::TRANSITION_MARKER);

            return;
        }

        // For new entities, allow setting initial state
        if ($entity->isNew()) {
            $initialState = $this->getWorkflowDefinition()->getInitialState()->getName();
            $newState = $entity->get($field);

            // Allow setting to initial state or leaving empty (will default to initial)
            if ($newState === null || $newState === '' || $newState === $initialState) {
                return;
            }

            throw new WorkflowException(
                "Cannot set initial state to '{$newState}'. New entities must start in the initial state '{$initialState}'.",
            );
        }

        // For existing entities, direct state changes are not allowed
        $originalState = $entity->getOriginal($field);
        $newState = $entity->get($field);

        throw new WorkflowException(
            "Direct state changes are not allowed. Use applyTransition() to change state from '{$originalState}' to '{$newState}'.",
        );
    }

    /**
     * Get the workflow definition.
     */
    public function getWorkflowDefinition(): Definition
    {
        if ($this->definition === null) {
            $registry = $this->getRegistry();
            $this->definition = $registry->getWorkflow($this->getConfig('workflow'));
        }

        return $this->definition;
    }

    /**
     * Get the workflow engine.
     */
    protected function getWorkflowEngine(): EngineInterface
    {
        if ($this->engine === null) {
            $registry = $this->getRegistry();
            $this->engine = $registry->getEngine($this->getConfig('workflow'));
        }

        return $this->engine;
    }

    /**
     * Check if a transition can be applied.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context
     */
    public function canTransition(EntityInterface $entity, string $transition, array $context = []): bool
    {
        return $this->getWorkflowEngine()->can(
            $this->getWorkflowDefinition(),
            $entity,
            $transition,
            $context,
        );
    }

    /**
     * Apply a transition to the entity.
     *
     * When autoSave is enabled, the entity is saved after a successful transition.
     * When autoLog is enabled, the transition is logged automatically.
     * When useLocking is enabled, acquires a lock before transitioning.
     * When useTransaction is enabled, wraps transition+save+log in a database transaction.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context Context data. Use 'reason' key for transition reason.
     */
    public function applyTransition(EntityInterface $entity, string $transition, array $context = []): TransitionResult
    {
        // Acquire lock if enabled (null=auto-detect, true=force, false=skip).
        // Optimistic locking is a lock-free alternative and takes precedence.
        $lock = null;
        $usedLock = false;
        if (!$this->getConfig('useOptimisticLock') && $this->shouldUseLocking()) {
            $lock = $this->acquireLock($entity, $context['_locked_by'] ?? null);
            if ($lock === null) {
                return TransitionResult::locked($this->getCurrentState($entity));
            }
            $usedLock = true;

            // While holding the mutex, re-read the persisted state so the engine
            // does not act on a stale in-memory value. Without this, two callers
            // that both loaded the entity in the same `from` state would each pass
            // the guard and re-apply the transition once they take turns on the
            // lock (lost update / double execution).
            $this->refreshWorkflowState($entity);
        }

        try {
            // Idempotency check happens inside the lock: a check before the lock
            // lets two concurrent callers with the same key both pass it (the log
            // row of the first is not yet committed). Holding the mutex serialises
            // them so the second sees the first's logged transition and is blocked.
            $idempotencyKey = $context['_idempotency_key'] ?? null;
            if ($idempotencyKey !== null && $this->isDuplicateTransition($entity, $transition, $idempotencyKey)) {
                return TransitionResult::blocked($this->getCurrentState($entity), [
                    'idempotency' => "Transition '{$transition}' already applied with this idempotency key",
                ]);
            }

            // Execute with or without transaction based on config
            if ($this->getConfig('useTransaction')) {
                return $this->executeTransitionInTransaction($entity, $transition, $context, $usedLock);
            }

            return $this->executeTransition($entity, $transition, $context, $usedLock);
        } finally {
            // Always release lock
            if ($lock !== null) {
                $this->releaseLock($entity);
            }
        }
    }

    /**
     * Apply and persist a transition as a single orchestration step.
     *
     * This is the high-level API for application code. It temporarily enables
     * save/log/lock/transaction behavior for this call without changing the
     * behavior's long-lived configuration.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context
     * @param array{save?: bool, log?: bool, lock?: bool|null, timeouts?: bool|null, transaction?: bool} $options
     */
    public function transition(
        EntityInterface $entity,
        string $transition,
        array $context = [],
        array $options = [],
    ): TransitionResult {
        $options += [
            'save' => true,
            'log' => true,
            'lock' => null,
            'timeouts' => null,
            'transaction' => true,
        ];

        return $this->withTemporaryConfig([
            'autoSave' => (bool)$options['save'],
            'autoLog' => (bool)$options['log'],
            'useLocking' => $options['lock'],
            'useTimeouts' => $options['timeouts'],
            'useTransaction' => (bool)$options['transaction'],
        ], fn (): TransitionResult => $this->applyTransition($entity, $transition, $context));
    }

    /**
     * Execute transition wrapped in a database transaction.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context
     * @param bool $usedLock
     */
    private function executeTransitionInTransaction(
        EntityInterface $entity,
        string $transition,
        array $context,
        bool $usedLock,
    ): TransitionResult {
        $connection = $this->_table->getConnection();

        $captured = null;
        $connection->transactional(function () use ($entity, $transition, $context, $usedLock, &$captured): bool {
            $result = $this->executeTransition($entity, $transition, $context, $usedLock);
            $captured = $result;

            if (!$result->isSuccess()) {
                // In optimistic mode a state claim (and any command writes) may already
                // be in this transaction; roll back so a lost/failed attempt leaves no
                // change. Otherwise commit (no changes were made for the failed result).
                return !$this->getConfig('useOptimisticLock');
            }

            // Auto-save if enabled - within transaction
            if ($this->getConfig('autoSave')) {
                $this->_table->saveOrFail($entity);
            }

            // Auto-log successful transitions within same transaction
            if ($this->getConfig('autoLog')) {
                $this->logTransition($entity, $result, $transition, $context);
            }

            if ($this->shouldSyncTimeouts()) {
                $this->syncTimeouts($entity);
            }

            return true;
        });

        /** @var \Workflow\Engine\TransitionResult $result */
        $result = $captured;

        // Log non-successful transitions outside the transaction for audit trail
        if (!$result->isSuccess() && $this->getConfig('autoLog') && $this->getConfig('logAllOutcomes')) {
            $this->logTransition($entity, $result, $transition, $context);
        }

        return $result;
    }

    /**
     * Execute the transition without transaction wrapper.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param array<string, mixed> $context
     * @param bool $usedLock
     */
    private function executeTransition(
        EntityInterface $entity,
        string $transition,
        array $context,
        bool $usedLock,
    ): TransitionResult {
        // Optimistic concurrency: claim the row BEFORE running commands, so a lost race
        // never executes side effects. Guards are evaluated first (no commands, no state
        // change); the claim then asserts the persisted state has not moved underneath us.
        // A claim made here is rolled back by executeTransitionInTransaction if anything
        // after it fails, so optimistic mode should keep useTransaction enabled.
        if ($this->getConfig('useOptimisticLock')) {
            $field = $this->getWorkflowDefinition()->getField();
            $from = (string)$entity->get($field);
            if ($this->canTransition($entity, $transition, $context)) {
                $to = $this->getWorkflowDefinition()->getTransition($transition)->getTo();
                if (!$this->claimOptimisticTransition($entity, $from, $to)) {
                    return TransitionResult::locked($from);
                }
            }
        }

        $result = $this->getWorkflowEngine()->apply(
            $this->getWorkflowDefinition(),
            $entity,
            $transition,
            $context,
        );

        if ($result->isSuccess()) {
            // Mark the entity so beforeSave knows this change came through the workflow
            $entity->set(self::TRANSITION_MARKER, true);

            $this->stampStateChangedAt($entity, $result);

            // Create new result with lock info if lock was used
            if ($usedLock) {
                $result = TransitionResult::success(
                    $result->getFromState(),
                    $result->getToState() ?? '',
                    $result->getGuardsEvaluated(),
                    $result->getCommandsExecuted(),
                    true,
                );
            }

            // If not using transaction, handle save/log here
            if (!$this->getConfig('useTransaction')) {
                if ($this->getConfig('autoSave')) {
                    $this->_table->saveOrFail($entity);
                }

                if ($this->getConfig('autoLog')) {
                    $this->logTransition($entity, $result, $transition, $context);
                }

                if ($this->shouldSyncTimeouts()) {
                    $this->syncTimeouts($entity);
                }
            }
        } elseif (!$this->getConfig('useTransaction') && $this->getConfig('autoLog') && $this->getConfig('logAllOutcomes')) {
            // Log non-successful transitions for audit trail when not using transactions
            $this->logTransition($entity, $result, $transition, $context);
        }

        return $result;
    }

    /**
     * Atomically claim the state change via compare-and-set:
     * `UPDATE ... SET state = :to WHERE pk = :id AND state = :from`.
     *
     * Returns false when no row matched (another writer already advanced it).
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $from Expected current state
     * @param string $to Target state
     *
     * @throws \Workflow\Exception\WorkflowException When the table has no single-column primary key.
     */
    private function claimOptimisticTransition(EntityInterface $entity, string $from, string $to): bool
    {
        $primaryKey = (array)$this->_table->getPrimaryKey();
        if (count($primaryKey) !== 1) {
            throw new WorkflowException('Optimistic locking requires a single-column primary key.');
        }

        $field = $this->getWorkflowDefinition()->getField();
        $primaryKeyField = (string)$primaryKey[0];

        $affected = $this->_table->updateAll(
            [$field => $to],
            [
                $primaryKeyField => $entity->get($primaryKeyField),
                $field => $from,
            ],
        );

        return $affected >= 1;
    }

    /**
     * Stamp the configured timestamp column with the current time on a real state
     * change. No-op when the column is disabled or absent from the table.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \Workflow\Engine\TransitionResult $result
     */
    private function stampStateChangedAt(EntityInterface $entity, TransitionResult $result): void
    {
        $field = $this->getConfig('stateTimestampField');
        if (!$field || $result->getToState() === $result->getFromState()) {
            return;
        }

        if (!$this->_table->getSchema()->hasColumn($field)) {
            return;
        }

        $entity->set($field, DateTime::now());
    }

    /**
     * Check if this is a duplicate transition based on idempotency key.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $transition
     * @param string $idempotencyKey
     */
    private function isDuplicateTransition(EntityInterface $entity, string $transition, string $idempotencyKey): bool
    {
        if (!$this->getConfig('autoLog')) {
            // Can't check idempotency without logging enabled
            return false;
        }

        $table = $this->fetchTable('Workflow.WorkflowTransitions');
        $foreignKey = (string)$entity->get('id');

        $existing = $table->find()
            ->where([
                'workflow_name' => $this->getConfig('workflow'),
                // Scope to the same entity table too: the same workflow name and
                // id could otherwise collide across tables.
                'model' => $this->getConfig('model'),
                'foreign_key' => $foreignKey,
                'transition_name' => $transition,
                // Only a previously SUCCESSFUL application counts as a duplicate;
                // a prior blocked/locked/error attempt with the same key must not
                // prevent a later legitimate retry.
                'status' => TransitionLogger::STATUS_SUCCESS,
                // Exact match on the dedicated column; the key is bound as a value,
                // so it is immune to LIKE wildcards and JSON escaping.
                'idempotency_key' => $idempotencyKey,
            ])
            ->first();

        return $existing !== null;
    }

    /**
     * Acquire a lock for the entity.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string|null $lockedBy
     */
    private function acquireLock(EntityInterface $entity, ?string $lockedBy = null): ?WorkflowLock
    {
        return $this->getLockManager()->acquire(
            $this->getConfig('workflow'),
            $this->getConfig('model'),
            $entity,
            $lockedBy,
        );
    }

    /**
     * Release a lock for the entity.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     */
    private function releaseLock(EntityInterface $entity): void
    {
        $this->getLockManager()->release(
            $this->getConfig('workflow'),
            $this->getConfig('model'),
            $entity,
        );
    }

    /**
     * Get the lock manager.
     */
    protected function getLockManager(): LockManager
    {
        if ($this->lockManager === null) {
            $this->lockManager = new LockManager();
        }

        return $this->lockManager;
    }

    public function setLockManager(LockManager $lockManager): static
    {
        $this->lockManager = $lockManager;

        return $this;
    }

    protected function getTimeoutScheduler(): TimeoutScheduler
    {
        if ($this->timeoutScheduler === null) {
            $this->timeoutScheduler = new TimeoutScheduler();
        }

        return $this->timeoutScheduler;
    }

    public function setTimeoutScheduler(TimeoutScheduler $timeoutScheduler): static
    {
        $this->timeoutScheduler = $timeoutScheduler;

        return $this;
    }

    /**
     * Determine if locking should be used.
     *
     * - null (default): auto-detect based on table existence
     * - true: always use locking
     * - false: never use locking
     */
    protected function shouldUseLocking(): bool
    {
        $config = $this->getConfig('useLocking');

        // Explicit true/false
        if ($config === true) {
            return true;
        }
        if ($config === false) {
            return false;
        }

        // Auto-detect: use locking if table exists
        return $this->lockTableExists();
    }

    protected function shouldSyncTimeouts(): bool
    {
        if (!$this->getConfig('autoSave')) {
            return false;
        }

        $config = $this->getConfig('useTimeouts');
        if ($config === true) {
            return true;
        }
        if ($config === false) {
            return false;
        }

        if (!(bool)Configure::read('Workflow.timeouts', true)) {
            return false;
        }

        return $this->timeoutsTableExists();
    }

    /**
     * Check if the workflow_locks table exists.
     *
     * Result is cached for the lifetime of this behavior instance.
     */
    protected function lockTableExists(): bool
    {
        if ($this->lockTableExists !== null) {
            return $this->lockTableExists;
        }

        try {
            $connection = $this->_table->getConnection();
            $schemaCollection = $connection->getSchemaCollection();
            $tables = $schemaCollection->listTables();
            $this->lockTableExists = in_array('workflow_locks', $tables, true);
        } catch (Throwable) {
            // If we can't check (e.g., no connection in tests), assume it doesn't exist
            $this->lockTableExists = false;
        }

        return $this->lockTableExists;
    }

    protected function timeoutsTableExists(): bool
    {
        if ($this->timeoutsTableExists !== null) {
            return $this->timeoutsTableExists;
        }

        try {
            $connection = $this->_table->getConnection();
            $schemaCollection = $connection->getSchemaCollection();
            $tables = $schemaCollection->listTables();
            $this->timeoutsTableExists = in_array('workflow_timeouts', $tables, true);
        } catch (Throwable) {
            $this->timeoutsTableExists = false;
        }

        return $this->timeoutsTableExists;
    }

    /**
     * Log a transition.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \Workflow\Engine\TransitionResult $result
     * @param string $transition
     * @param array<string, mixed> $context
     */
    protected function logTransition(
        EntityInterface $entity,
        TransitionResult $result,
        string $transition,
        array $context,
    ): void {
        $definition = $this->getWorkflowDefinition();
        $this->getLogger()->log(
            $this->getConfig('workflow'),
            $this->getConfig('model'),
            $entity,
            $result,
            $transition,
            $context,
            (string)$definition->getVersion(),
        );
    }

    /**
     * Get the transition logger.
     */
    protected function getLogger(): TransitionLogger
    {
        if ($this->logger === null) {
            $this->logger = new TransitionLogger();
        }

        return $this->logger;
    }

    public function setLogger(TransitionLogger $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    protected function syncTimeouts(EntityInterface $entity): void
    {
        $definition = $this->getWorkflowDefinition();
        $currentState = $this->getCurrentState($entity);
        $state = $definition->getState($currentState);

        $this->getTimeoutScheduler()->syncStateTimeouts(
            $this->getConfig('workflow'),
            $this->getConfig('model'),
            $entity,
            $state,
        );
    }

    /**
     * Get available transitions for the entity.
     *
     * @return array<string>
     */
    public function getAvailableTransitions(EntityInterface $entity): array
    {
        return $this->getWorkflowEngine()->getAvailableTransitions(
            $this->getWorkflowDefinition(),
            $entity,
        );
    }

    /**
     * Get the current state of the entity.
     */
    public function getCurrentState(EntityInterface $entity): string
    {
        return $this->getWorkflowEngine()->getCurrentState(
            $this->getWorkflowDefinition(),
            $entity,
        );
    }

    /**
     * Re-read the persisted state field from the database onto the entity.
     *
     * Called while holding the lock so the engine evaluates the transition against
     * the authoritative current state rather than a stale in-memory value. Only the
     * state field is touched (and its dirty flag cleared so `beforeSave` does not
     * treat the refresh as a direct state change). No-op for unpersisted entities.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     */
    protected function refreshWorkflowState(EntityInterface $entity): void
    {
        if ($entity->isNew()) {
            return;
        }

        $primaryKey = (array)$this->_table->getPrimaryKey();
        $conditions = [];
        foreach ($primaryKey as $column) {
            $value = $entity->get($column);
            if ($value === null) {
                return;
            }
            $conditions[$this->_table->aliasField($column)] = $value;
        }

        $field = $this->getWorkflowDefinition()->getField();

        /** @var array<string, mixed>|null $row */
        $row = $this->_table->find()
            ->select([$field])
            ->where($conditions)
            ->disableHydration()
            ->first();

        if ($row === null) {
            return;
        }

        $entity->set($field, $row[$field]);
        $entity->setDirty($field, false);
    }

    /**
     * Check if the entity is in a specific state.
     */
    public function isInState(EntityInterface $entity, string $state): bool
    {
        return $this->getCurrentState($entity) === $state;
    }

    /**
     * Check if the entity is in a final state.
     */
    public function isFinal(EntityInterface $entity): bool
    {
        $currentState = $this->getCurrentState($entity);
        $stateObj = $this->getWorkflowDefinition()->resolveState($currentState);

        return $stateObj->isFinal();
    }

    /**
     * Check if the current state has a specific flag.
     */
    public function hasFlag(EntityInterface $entity, string $flag): bool
    {
        $currentState = $this->getCurrentState($entity);
        $stateObj = $this->getWorkflowDefinition()->resolveState($currentState);

        return $stateObj->hasFlag($flag);
    }

    /**
     * Custom finder to get entities in states with a specific flag.
     *
     * Usage:
     * ```php
     * // Find all completed orders (states with 'done' flag)
     * $completedOrders = $this->Orders->find('withFlag', flag: 'done');
     *
     * // Find all failed orders
     * $failedOrders = $this->Orders->find('withFlag', flag: 'failed');
     * ```
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param string $flag The flag to filter by
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findWithFlag(SelectQuery $query, string $flag): SelectQuery
    {
        $stateNames = $this->getStateNamesWithFlag($flag);

        if (!$stateNames) {
            // No states have this flag, return empty result
            $query->where(['1 = 0']);

            return $query;
        }

        $field = $this->getWorkflowDefinition()->getField();

        return $query->where([
            $this->_table->aliasField($field) . ' IN' => $stateNames,
        ]);
    }

    /**
     * Custom finder to get entities NOT in states with a specific flag.
     *
     * Usage:
     * ```php
     * // Find all orders that are not yet done
     * $pendingOrders = $this->Orders->find('withoutFlag', flag: 'done');
     *
     * // Find all orders that haven't failed
     * $activeOrders = $this->Orders->find('withoutFlag', flag: 'failed');
     * ```
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param string $flag The flag to exclude
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findWithoutFlag(SelectQuery $query, string $flag): SelectQuery
    {
        $stateNames = $this->getStateNamesWithFlag($flag);

        if (!$stateNames) {
            // No states have this flag, so all entities match
            return $query;
        }

        $field = $this->getWorkflowDefinition()->getField();

        return $query->where([
            $this->_table->aliasField($field) . ' NOT IN' => $stateNames,
        ]);
    }

    /**
     * Custom finder to get entities in final states.
     *
     * Usage:
     * ```php
     * $completedOrders = $this->Orders->find('inFinalState');
     * ```
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findInFinalState(SelectQuery $query): SelectQuery
    {
        $stateNames = $this->getFinalStateNames();

        if (!$stateNames) {
            $query->where(['1 = 0']);

            return $query;
        }

        $field = $this->getWorkflowDefinition()->getField();

        return $query->where([
            $this->_table->aliasField($field) . ' IN' => $stateNames,
        ]);
    }

    /**
     * Custom finder to get entities NOT in final states (still active).
     *
     * Usage:
     * ```php
     * $activeOrders = $this->Orders->find('notInFinalState');
     * ```
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findNotInFinalState(SelectQuery $query): SelectQuery
    {
        $stateNames = $this->getFinalStateNames();

        if (!$stateNames) {
            // No final states, all entities are active
            return $query;
        }

        $field = $this->getWorkflowDefinition()->getField();

        return $query->where([
            $this->_table->aliasField($field) . ' NOT IN' => $stateNames,
        ]);
    }

    /**
     * Custom finder to get entities in a specific state.
     *
     * Usage:
     * ```php
     * $pendingOrders = $this->Orders->find('inState', state: 'pending');
     * ```
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param string $state The state name to filter by
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findInState(SelectQuery $query, string $state): SelectQuery
    {
        $field = $this->getWorkflowDefinition()->getField();

        return $query->where([
            $this->_table->aliasField($field) => $state,
        ]);
    }

    /**
     * Get state names that have a specific flag.
     *
     * @param string $flag
     *
     * @return array<string>
     */
    public function getStateNamesWithFlag(string $flag): array
    {
        $states = $this->getWorkflowDefinition()->getStatesWithFlag($flag);

        return array_map(fn ($state) => $state->getName(), $states);
    }

    /**
     * Get state names that do NOT have a specific flag.
     *
     * @param string $flag
     *
     * @return array<string>
     */
    public function getStateNamesWithoutFlag(string $flag): array
    {
        $definition = $this->getWorkflowDefinition();
        $allStates = $definition->getStates();
        $flaggedStates = $this->getStateNamesWithFlag($flag);

        return array_values(array_filter(
            array_map(fn ($state) => $state->getName(), $allStates),
            fn ($name) => !in_array($name, $flaggedStates, true),
        ));
    }

    /**
     * Get names of all final states.
     *
     * @return array<string>
     */
    public function getFinalStateNames(): array
    {
        $definition = $this->getWorkflowDefinition();

        return array_values(array_map(
            fn ($state) => $state->getName(),
            array_filter(
                $definition->getStates(),
                fn ($state) => $state->isFinal(),
            ),
        ));
    }

    /**
     * Get the workflow registry.
     *
     * @throws \Workflow\Exception\WorkflowException
     */
    protected function getRegistry(): WorkflowRegistry
    {
        $registry = $this->getConfig('registry');
        if ($registry instanceof WorkflowRegistry) {
            return $registry;
        }

        $registry = WorkflowRegistryLocator::get() ?? Configure::read('Workflow.registry');
        if (!$registry instanceof WorkflowRegistry) {
            throw new WorkflowException('WorkflowBehavior requires a registry instance');
        }

        return $registry;
    }

    /**
     * @param array<string, mixed> $config
     * @param \Closure(): \Workflow\Engine\TransitionResult $callback
     */
    private function withTemporaryConfig(array $config, Closure $callback): TransitionResult
    {
        $originalConfig = [];
        foreach ($config as $key => $value) {
            $originalConfig[$key] = $this->getConfig($key);
        }

        $this->setConfig($config);

        try {
            return $callback();
        } finally {
            $this->setConfig($originalConfig);
        }
    }
}
