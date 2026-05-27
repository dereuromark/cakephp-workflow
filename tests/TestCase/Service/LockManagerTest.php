<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Service;

use Cake\I18n\DateTime;
use Cake\ORM\Entity;
use Workflow\Service\LockManager;
use Workflow\Test\TestCase\DatabaseTestCase;

class LockManagerTest extends DatabaseTestCase
{
    private LockManager $lockManager;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();
        $this->lockManager = new LockManager(30);
    }

    public function testAcquireLock(): void
    {
        $entity = new Entity(['id' => '123']);

        $lock = $this->lockManager->acquire('order', 'Orders', $entity, 'user-1');

        $this->assertNotNull($lock);
        $this->assertSame('order', $lock->workflow_name);
        $this->assertSame('Orders', $lock->model);
        $this->assertSame(123, $lock->foreign_key);
        $this->assertSame('user-1', $lock->locked_by);
        $this->assertInstanceOf(DateTime::class, $lock->expires_at);
    }

    public function testAcquireLockWithoutLockedBy(): void
    {
        $entity = new Entity(['id' => '123']);

        $lock = $this->lockManager->acquire('order', 'Orders', $entity);

        $this->assertNotNull($lock);
        $this->assertNull($lock->locked_by);
    }

    public function testAcquireLockFailsWhenAlreadyLocked(): void
    {
        $entity = new Entity(['id' => '123']);

        // First lock should succeed
        $lock1 = $this->lockManager->acquire('order', 'Orders', $entity, 'user-1');
        $this->assertNotNull($lock1);

        // Second lock on same entity should fail
        $lock2 = $this->lockManager->acquire('order', 'Orders', $entity, 'user-2');
        $this->assertNull($lock2);
    }

    public function testAcquireLockSucceedsForDifferentEntities(): void
    {
        $entity1 = new Entity(['id' => '123']);
        $entity2 = new Entity(['id' => '456']);

        $lock1 = $this->lockManager->acquire('order', 'Orders', $entity1, 'user-1');
        $lock2 = $this->lockManager->acquire('order', 'Orders', $entity2, 'user-2');

        $this->assertNotNull($lock1);
        $this->assertNotNull($lock2);
    }

    public function testAcquireLockSucceedsForDifferentWorkflows(): void
    {
        $entity = new Entity(['id' => '123']);

        $lock1 = $this->lockManager->acquire('order', 'Orders', $entity, 'user-1');
        $lock2 = $this->lockManager->acquire('payment', 'Orders', $entity, 'user-2');

        $this->assertNotNull($lock1);
        $this->assertNotNull($lock2);
    }

    public function testIsLocked(): void
    {
        $entity = new Entity(['id' => '123']);

        // Initially not locked
        $this->assertFalse($this->lockManager->isLocked('order', 'Orders', $entity));

        // Acquire lock
        $this->lockManager->acquire('order', 'Orders', $entity, 'user-1');

        // Now locked
        $this->assertTrue($this->lockManager->isLocked('order', 'Orders', $entity));
    }

    public function testReleaseLock(): void
    {
        $entity = new Entity(['id' => '123']);

        // Acquire lock
        $this->lockManager->acquire('order', 'Orders', $entity, 'user-1');
        $this->assertTrue($this->lockManager->isLocked('order', 'Orders', $entity));

        // Release lock
        $this->lockManager->release('order', 'Orders', $entity);
        $this->assertFalse($this->lockManager->isLocked('order', 'Orders', $entity));
    }

    public function testReleaseAllowsNewLock(): void
    {
        $entity = new Entity(['id' => '123']);

        // Acquire and release
        $this->lockManager->acquire('order', 'Orders', $entity, 'user-1');
        $this->lockManager->release('order', 'Orders', $entity);

        // Should be able to acquire again
        $lock = $this->lockManager->acquire('order', 'Orders', $entity, 'user-2');
        $this->assertNotNull($lock);
        $this->assertSame('user-2', $lock->locked_by);
    }

    public function testExpiredLockIsNotConsideredLocked(): void
    {
        // Create a LockManager with very short duration
        $lockManager = new LockManager(1);
        $entity = new Entity(['id' => '123']);

        // Acquire lock
        $lockManager->acquire('order', 'Orders', $entity, 'user-1');

        // Wait for lock to expire
        sleep(2);

        // Should not be locked anymore
        $this->assertFalse($lockManager->isLocked('order', 'Orders', $entity));
    }

    public function testExpiredLockAllowsNewAcquisition(): void
    {
        // Create a LockManager with very short duration
        $lockManager = new LockManager(1);
        $entity = new Entity(['id' => '123']);

        // Acquire lock
        $lockManager->acquire('order', 'Orders', $entity, 'user-1');

        // Wait for lock to expire
        sleep(2);

        // Should be able to acquire new lock
        $lock = $lockManager->acquire('order', 'Orders', $entity, 'user-2');
        $this->assertNotNull($lock);
        $this->assertSame('user-2', $lock->locked_by);
    }

    public function testLockExpirationUsesUtc(): void
    {
        $entity = new Entity(['id' => '123']);

        $lock = $this->lockManager->acquire('order', 'Orders', $entity, 'user-1');

        $this->assertNotNull($lock);
        // The expires_at should be approximately now + 30 seconds
        $now = DateTime::now('UTC');
        $diff = $lock->expires_at->diffInSeconds($now);

        // Should be close to 30 seconds (allow some tolerance)
        $this->assertGreaterThan(25, $diff);
        $this->assertLessThanOrEqual(30, $diff);
    }

    public function testDefaultLockDurationFromConfig(): void
    {
        // Test without specifying duration (uses config default)
        $lockManager = new LockManager();
        $entity = new Entity(['id' => '999']);

        $lock = $lockManager->acquire('order', 'Orders', $entity);

        $this->assertNotNull($lock);
    }

    public function testAcquireLockWithUuidForeignKey(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $entity = new Entity(['id' => $uuid]);
        $this->fetchTable('Workflow.WorkflowLocks')->getSchema()->setColumnType('foreign_key', 'string');

        $lock = $this->lockManager->acquire('order', 'Orders', $entity, 'user-1');

        $this->assertNotNull($lock);
        $this->assertSame($uuid, $lock->foreign_key);
    }
}
