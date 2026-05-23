<?php

declare(strict_types=1);

namespace Workflow\Service;

use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use PDOException;
use Throwable;
use Workflow\Model\Entity\WorkflowLock;
use Workflow\Model\Table\WorkflowLocksTable;

class LockManager
{
    use LocatorAwareTrait;

    private int $lockDurationSeconds;

    public function __construct(?int $lockDurationSeconds = null)
    {
        $this->lockDurationSeconds = $lockDurationSeconds
            ?? Configure::read('Workflow.lockDuration', 30);
    }

    /**
     * Attempt to acquire a lock on an entity.
     */
    public function acquire(
        string $workflowName,
        string $model,
        EntityInterface $entity,
        ?string $lockedBy = null,
    ): ?WorkflowLock {
        /** @var \Workflow\Model\Table\WorkflowLocksTable $table */
        $table = $this->fetchTable('Workflow.WorkflowLocks');
        $foreignKey = (string)$entity->get('id');

        // Fast path: someone already holds an active lock for this entity.
        $existing = $table->find('activeLock', workflow: $workflowName, table: $model, id: $foreignKey)->first();
        if ($existing !== null) {
            return null;
        }

        // Try to take the lock. The unique index (workflow_name, model,
        // foreign_key) is the real mutex and the INSERT is the atomic acquire.
        // We deliberately do NOT run a global expired-lock sweep here: a range
        // DELETE on the lock table under high insert concurrency causes InnoDB
        // gap-lock deadlocks. Expired rows for THIS entity are reclaimed on demand
        // below; cluster-wide GC of other entities' expired rows is left to
        // deleteExpired() (e.g. a maintenance command).
        $lock = $this->insert($table, $workflowName, $model, $foreignKey, $lockedBy);
        if ($lock !== null) {
            return $lock;
        }

        // Insert collided with an existing row. If that row is merely expired,
        // reclaim it (scoped point delete) and retry once; otherwise it is an
        // active lock held elsewhere.
        $stale = $table->find()
            ->where([
                'workflow_name' => $workflowName,
                'model' => $model,
                'foreign_key' => $foreignKey,
                'expires_at <' => $this->currentTime(),
            ])
            ->first();
        if ($stale === null) {
            return null;
        }

        $table->deleteAll([
            'workflow_name' => $workflowName,
            'model' => $model,
            'foreign_key' => $foreignKey,
            'expires_at <' => $this->currentTime(),
        ]);

        return $this->insert($table, $workflowName, $model, $foreignKey, $lockedBy);
    }

    /**
     * Insert a fresh lock row, returning the lock on success or null when the
     * acquire could not be taken (active lock present, or transient contention).
     *
     * Unique-constraint violations (a concurrent acquirer won the race) and
     * deadlocks (transient contention on the lock table) both map to "locked"
     * (null) rather than escaping as an exception; the caller can treat that as
     * "try again later".
     *
     * @param \Workflow\Model\Table\WorkflowLocksTable $table
     * @param string $workflowName
     * @param string $model
     * @param string $foreignKey
     * @param string|null $lockedBy
     */
    protected function insert(
        WorkflowLocksTable $table,
        string $workflowName,
        string $model,
        string $foreignKey,
        ?string $lockedBy,
    ): ?WorkflowLock {
        /** @var \Workflow\Model\Entity\WorkflowLock $lock */
        $lock = $table->newEntity([
            'workflow_name' => $workflowName,
            'model' => $model,
            'foreign_key' => $foreignKey,
            'locked_by' => $lockedBy,
            'expires_at' => DateTime::now()->addSeconds($this->lockDurationSeconds),
        ]);

        try {
            if ($table->save($lock)) {
                return $lock;
            }
        } catch (Throwable $e) {
            // save() raises an integrity-violation (unique) or serialization
            // failure (deadlock) under concurrent acquires; both mean "could not
            // take the lock right now" rather than a fatal error. Anything else
            // is a real failure and is rethrown.
            if ($this->isUniqueViolation($e) || $this->isDeadlock($e)) {
                return null;
            }

            throw $e;
        }

        return null;
    }

    /**
     * Whether the given exception is a deadlock / serialization failure
     * (SQLSTATE 40001), raised under transient contention on the lock table.
     */
    protected function isDeadlock(Throwable $e): bool
    {
        return $this->matchesSqlState($e, '40001');
    }

    /**
     * Whether the given exception is a unique/integrity constraint violation
     * (SQLSTATE 23000), raised when two processes race to insert the same lock.
     */
    protected function isUniqueViolation(Throwable $e): bool
    {
        return $this->matchesSqlState($e, '23000');
    }

    /**
     * Whether the exception (or its PDO cause) carries the given SQLSTATE code.
     */
    protected function matchesSqlState(Throwable $e, string $sqlState): bool
    {
        $previous = $e->getPrevious();
        if ($previous instanceof PDOException) {
            if ((string)$previous->getCode() === $sqlState) {
                return true;
            }
            if (isset($previous->errorInfo[0]) && (string)$previous->errorInfo[0] === $sqlState) {
                return true;
            }
        }

        if ($e instanceof PDOException && (string)$e->getCode() === $sqlState) {
            return true;
        }

        return str_contains($e->getMessage(), 'SQLSTATE[' . $sqlState . ']');
    }

    /**
     * Current time used for expiry comparisons, in the app's configured timezone
     * (the same basis lock rows are written with).
     */
    protected function currentTime(): DateTime
    {
        return DateTime::now();
    }

    /**
     * Check if an entity is locked.
     */
    public function isLocked(
        string $workflowName,
        string $model,
        EntityInterface $entity,
    ): bool {
        $table = $this->fetchTable('Workflow.WorkflowLocks');

        $lock = $table->find(
            'activeLock',
            workflow: $workflowName,
            table: $model,
            id: (string)$entity->get('id'),
        )->first();

        return $lock !== null;
    }

    /**
     * Release a lock on an entity.
     */
    public function release(
        string $workflowName,
        string $model,
        EntityInterface $entity,
    ): void {
        $table = $this->fetchTable('Workflow.WorkflowLocks');

        $table->deleteAll([
            'workflow_name' => $workflowName,
            'model' => $model,
            'foreign_key' => (string)$entity->get('id'),
        ]);
    }
}
