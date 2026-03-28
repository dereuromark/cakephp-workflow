<?php
declare(strict_types=1);

namespace Workflow\Service;

use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Workflow\Model\Entity\WorkflowLock;

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
        string $entityTable,
        EntityInterface $entity,
        ?string $lockedBy = null,
    ): ?WorkflowLock {
        /** @var \Workflow\Model\Table\WorkflowLocksTable $table */
        $table = $this->fetchTable('Workflow.WorkflowLocks');
        $entityId = (string) $entity->get('id');

        // Clean up expired locks first
        $table->deleteExpired();

        // Check for existing active lock
        $existing = $table->find('activeLock', [
            'workflow' => $workflowName,
            'table' => $entityTable,
            'id' => $entityId,
        ])->first();

        if ($existing !== null) {
            return null;
        }

        // Delete any stale lock record for this entity
        $table->deleteAll([
            'workflow_name' => $workflowName,
            'entity_table' => $entityTable,
            'entity_id' => $entityId,
        ]);

        // Create new lock
        /** @var \Workflow\Model\Entity\WorkflowLock $lock */
        $lock = $table->newEntity([
            'workflow_name' => $workflowName,
            'entity_table' => $entityTable,
            'entity_id' => $entityId,
            'locked_by' => $lockedBy,
            'expires_at' => DateTime::now()->addSeconds($this->lockDurationSeconds),
        ]);

        if ($table->save($lock)) {
            return $lock;
        }

        return null;
    }

    /**
     * Check if an entity is locked.
     */
    public function isLocked(
        string $workflowName,
        string $entityTable,
        EntityInterface $entity,
    ): bool {
        $table = $this->fetchTable('Workflow.WorkflowLocks');

        $lock = $table->find('activeLock', [
            'workflow' => $workflowName,
            'table' => $entityTable,
            'id' => (string) $entity->get('id'),
        ])->first();

        return $lock !== null;
    }

    /**
     * Release a lock on an entity.
     */
    public function release(
        string $workflowName,
        string $entityTable,
        EntityInterface $entity,
    ): void {
        $table = $this->fetchTable('Workflow.WorkflowLocks');

        $table->deleteAll([
            'workflow_name' => $workflowName,
            'entity_table' => $entityTable,
            'entity_id' => (string) $entity->get('id'),
        ]);
    }
}
