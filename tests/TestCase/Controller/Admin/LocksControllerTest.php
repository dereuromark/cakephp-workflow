<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Controller\Admin;

use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\DateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Workflow\Test\TestCase\Controller\IntegrationTestCase;

/**
 * @uses \Workflow\Controller\Admin\LocksController
 */
#[AllowMockObjectsWithoutExpectations]
class LocksControllerTest extends IntegrationTestCase
{
    /**
     * Test index action renders successfully.
     */
    public function testIndex(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'index',
        ]);

        $this->assertResponseOk();
    }

    /**
     * Test index action shows active locks by default.
     */
    public function testIndexShowsActiveLocks(): void
    {
        $locksTable = $this->fetchTable('Workflow.WorkflowLocks');

        // Add an active lock
        $locksTable->save($locksTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'locked_by' => 'user-1',
            'expires_at' => DateTime::now()->addMinutes(30),
            'created' => DateTime::now(),
        ]));

        // Add an expired lock
        $locksTable->save($locksTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '456',
            'locked_by' => 'user-2',
            'expires_at' => DateTime::now()->subMinutes(30),
            'created' => DateTime::now()->subHours(1),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $locks = $this->viewVariable('locks');
        $this->assertCount(1, $locks);
        $this->assertSame('123', $locks->items()->first()->entity_id);
    }

    /**
     * Test index action filters by expired status.
     */
    public function testIndexFiltersExpiredLocks(): void
    {
        $locksTable = $this->fetchTable('Workflow.WorkflowLocks');

        // Add an active lock
        $locksTable->save($locksTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'locked_by' => 'user-1',
            'expires_at' => DateTime::now()->addMinutes(30),
            'created' => DateTime::now(),
        ]));

        // Add an expired lock
        $locksTable->save($locksTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '456',
            'locked_by' => 'user-2',
            'expires_at' => DateTime::now()->subMinutes(30),
            'created' => DateTime::now()->subHours(1),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'index',
            '?' => ['status' => 'expired'],
        ]);

        $this->assertResponseOk();

        $locks = $this->viewVariable('locks');
        $this->assertCount(1, $locks);
        $this->assertSame('456', $locks->items()->first()->entity_id);
    }

    /**
     * Test index action filters by workflow.
     */
    public function testIndexFiltersWorkflow(): void
    {
        $locksTable = $this->fetchTable('Workflow.WorkflowLocks');

        // Add locks for different workflows
        $locksTable->save($locksTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'locked_by' => 'user-1',
            'expires_at' => DateTime::now()->addMinutes(30),
            'created' => DateTime::now(),
        ]));

        $locksTable->save($locksTable->newEntity([
            'workflow_name' => 'payment',
            'entity_table' => 'Payments',
            'entity_id' => '456',
            'locked_by' => 'user-2',
            'expires_at' => DateTime::now()->addMinutes(30),
            'created' => DateTime::now(),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'index',
            '?' => ['workflow' => 'order'],
        ]);

        $this->assertResponseOk();

        $locks = $this->viewVariable('locks');
        $this->assertCount(1, $locks);
        $this->assertSame('order', $locks->items()->first()->workflow_name);
    }

    /**
     * Test index action shows all locks when status is 'all'.
     */
    public function testIndexShowsAllLocks(): void
    {
        $locksTable = $this->fetchTable('Workflow.WorkflowLocks');

        // Add an active lock
        $locksTable->save($locksTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'locked_by' => 'user-1',
            'expires_at' => DateTime::now()->addMinutes(30),
            'created' => DateTime::now(),
        ]));

        // Add an expired lock
        $locksTable->save($locksTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '456',
            'locked_by' => 'user-2',
            'expires_at' => DateTime::now()->subMinutes(30),
            'created' => DateTime::now()->subHours(1),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'index',
            '?' => ['status' => 'all'],
        ]);

        $this->assertResponseOk();

        $locks = $this->viewVariable('locks');
        $this->assertCount(2, $locks);
    }

    /**
     * Test index action provides workflow names for filter.
     */
    public function testIndexProvidesWorkflowNames(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $workflowNames = $this->viewVariable('workflowNames');
        $this->assertIsArray($workflowNames);
        $this->assertContains('order', $workflowNames);
        $this->assertContains('payment', $workflowNames);
    }

    /**
     * Test release action requires POST method.
     */
    public function testReleaseRequiresPost(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'release',
            1,
        ]);

        $this->assertResponseCode(405);
    }

    /**
     * Test release action deletes the lock.
     */
    public function testReleaseDeletesLock(): void
    {
        $this->enableRetainFlashMessages();

        $locksTable = $this->fetchTable('Workflow.WorkflowLocks');
        $lock = $locksTable->save($locksTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'locked_by' => 'user-1',
            'expires_at' => DateTime::now()->addMinutes(30),
            'created' => DateTime::now(),
        ]));

        $this->post([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'release',
            $lock->id,
        ]);

        $this->assertRedirect([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'index',
        ]);
        $this->assertFlashMessage('Lock released successfully.');

        // Verify lock is deleted
        $this->assertNull($locksTable->find()->where(['id' => $lock->id])->first());
    }

    /**
     * Test release action with non-existent lock.
     */
    public function testReleaseNonExistentLock(): void
    {
        $this->disableErrorHandlerMiddleware();
        $this->expectException(RecordNotFoundException::class);

        $this->post([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'release',
            9999,
        ]);
    }

    /**
     * Test cleanup action requires POST method.
     */
    public function testCleanupRequiresPost(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'cleanup',
        ]);

        $this->assertResponseCode(405);
    }

    /**
     * Test cleanup action deletes expired locks.
     */
    public function testCleanupDeletesExpiredLocks(): void
    {
        $this->enableRetainFlashMessages();

        $locksTable = $this->fetchTable('Workflow.WorkflowLocks');

        // Add an active lock
        $locksTable->save($locksTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'locked_by' => 'user-1',
            'expires_at' => DateTime::now()->addMinutes(30),
            'created' => DateTime::now(),
        ]));

        // Add expired locks
        $locksTable->save($locksTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '456',
            'locked_by' => 'user-2',
            'expires_at' => DateTime::now()->subMinutes(30),
            'created' => DateTime::now()->subHours(1),
        ]));

        $locksTable->save($locksTable->newEntity([
            'workflow_name' => 'payment',
            'entity_table' => 'Payments',
            'entity_id' => '789',
            'locked_by' => 'user-3',
            'expires_at' => DateTime::now()->subMinutes(60),
            'created' => DateTime::now()->subHours(2),
        ]));

        $this->post([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'cleanup',
        ]);

        $this->assertRedirect([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'index',
        ]);
        $this->assertFlashMessage('2 expired lock(s) cleaned up.');

        // Verify only active lock remains
        $this->assertSame(1, $locksTable->find()->count());
        $this->assertNotNull($locksTable->find()->where(['entity_id' => '123'])->first());
    }

    /**
     * Test cleanup action with no expired locks.
     */
    public function testCleanupWithNoExpiredLocks(): void
    {
        $this->enableRetainFlashMessages();

        $locksTable = $this->fetchTable('Workflow.WorkflowLocks');

        // Add only an active lock
        $locksTable->save($locksTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'locked_by' => 'user-1',
            'expires_at' => DateTime::now()->addMinutes(30),
            'created' => DateTime::now(),
        ]));

        $this->post([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Locks',
            'action' => 'cleanup',
        ]);

        $this->assertRedirect();
        $this->assertFlashMessage('0 expired lock(s) cleaned up.');
    }
}
