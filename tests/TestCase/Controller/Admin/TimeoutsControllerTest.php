<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Controller\Admin;

use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\DateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Workflow\Test\TestCase\Controller\IntegrationTestCase;

/**
 * @uses \Workflow\Controller\Admin\TimeoutsController
 */
#[AllowMockObjectsWithoutExpectations]
class TimeoutsControllerTest extends IntegrationTestCase
{
    /**
     * Test index action renders successfully.
     */
    public function testIndex(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'index',
        ]);

        $this->assertResponseOk();
    }

    /**
     * Test index action shows pending timeouts by default.
     */
    public function testIndexShowsPendingTimeouts(): void
    {
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');

        // Add a pending timeout
        $timeoutsTable->save($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'current_state' => 'pending',
            'transition_name' => 'auto_cancel',
            'due_at' => DateTime::now()->addHours(1),
            'processed' => false,
            'created' => DateTime::now(),
        ]));

        // Add a processed timeout
        $timeoutsTable->save($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '456',
            'current_state' => 'pending',
            'transition_name' => 'auto_cancel',
            'due_at' => DateTime::now()->subHours(1),
            'processed' => true,
            'created' => DateTime::now()->subHours(2),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $timeouts = $this->viewVariable('timeouts');
        $this->assertCount(1, $timeouts);
        $this->assertSame('123', $timeouts->items()->first()->entity_id);
    }

    /**
     * Test index action filters by processed status.
     */
    public function testIndexFiltersProcessedTimeouts(): void
    {
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');

        // Add a pending timeout
        $timeoutsTable->save($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'current_state' => 'pending',
            'transition_name' => 'auto_cancel',
            'due_at' => DateTime::now()->addHours(1),
            'processed' => false,
            'created' => DateTime::now(),
        ]));

        // Add a processed timeout
        $timeoutsTable->save($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '456',
            'current_state' => 'pending',
            'transition_name' => 'auto_cancel',
            'due_at' => DateTime::now()->subHours(1),
            'processed' => true,
            'created' => DateTime::now()->subHours(2),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'index',
            '?' => ['status' => 'processed'],
        ]);

        $this->assertResponseOk();

        $timeouts = $this->viewVariable('timeouts');
        $this->assertCount(1, $timeouts);
        $this->assertSame('456', $timeouts->items()->first()->entity_id);
    }

    /**
     * Test index action shows all timeouts when status is 'all'.
     */
    public function testIndexShowsAllTimeouts(): void
    {
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');

        // Add a pending timeout
        $timeoutsTable->save($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'current_state' => 'pending',
            'transition_name' => 'auto_cancel',
            'due_at' => DateTime::now()->addHours(1),
            'processed' => false,
            'created' => DateTime::now(),
        ]));

        // Add a processed timeout
        $timeoutsTable->save($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '456',
            'current_state' => 'pending',
            'transition_name' => 'auto_cancel',
            'due_at' => DateTime::now()->subHours(1),
            'processed' => true,
            'created' => DateTime::now()->subHours(2),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'index',
            '?' => ['status' => 'all'],
        ]);

        $this->assertResponseOk();

        $timeouts = $this->viewVariable('timeouts');
        $this->assertCount(2, $timeouts);
    }

    /**
     * Test index action filters by workflow.
     */
    public function testIndexFiltersWorkflow(): void
    {
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');

        // Add timeouts for different workflows
        $timeoutsTable->save($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'current_state' => 'pending',
            'transition_name' => 'auto_cancel',
            'due_at' => DateTime::now()->addHours(1),
            'processed' => false,
            'created' => DateTime::now(),
        ]));

        $timeoutsTable->save($timeoutsTable->newEntity([
            'workflow_name' => 'payment',
            'entity_table' => 'Payments',
            'entity_id' => '456',
            'current_state' => 'pending',
            'transition_name' => 'auto_fail',
            'due_at' => DateTime::now()->addHours(1),
            'processed' => false,
            'created' => DateTime::now(),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'index',
            '?' => ['workflow' => 'order'],
        ]);

        $this->assertResponseOk();

        $timeouts = $this->viewVariable('timeouts');
        $this->assertCount(1, $timeouts);
        $this->assertSame('order', $timeouts->items()->first()->workflow_name);
    }

    /**
     * Test index action provides workflow names for filter.
     */
    public function testIndexProvidesWorkflowNames(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $workflowNames = $this->viewVariable('workflowNames');
        $this->assertIsArray($workflowNames);
        $this->assertContains('order', $workflowNames);
        $this->assertContains('payment', $workflowNames);
    }

    /**
     * Test index action orders by due_at ASC.
     */
    public function testIndexOrdersByDueDate(): void
    {
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');

        // Add timeout due later
        $timeoutsTable->save($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'current_state' => 'pending',
            'transition_name' => 'auto_cancel',
            'due_at' => DateTime::now()->addHours(2),
            'processed' => false,
            'created' => DateTime::now(),
        ]));

        // Add timeout due sooner
        $timeoutsTable->save($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '456',
            'current_state' => 'pending',
            'transition_name' => 'auto_cancel',
            'due_at' => DateTime::now()->addHours(1),
            'processed' => false,
            'created' => DateTime::now(),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $timeouts = $this->viewVariable('timeouts')->toArray();
        $this->assertCount(2, $timeouts);
        // Soonest due date first
        $this->assertSame('456', $timeouts[0]->entity_id);
        $this->assertSame('123', $timeouts[1]->entity_id);
    }

    /**
     * Test cancel action requires POST method.
     */
    public function testCancelRequiresPost(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'cancel',
            1,
        ]);

        $this->assertResponseCode(405);
    }

    /**
     * Test cancel action marks timeout as processed.
     */
    public function testCancelMarksAsProcessed(): void
    {
        $this->enableRetainFlashMessages();

        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $timeout = $timeoutsTable->save($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'current_state' => 'pending',
            'transition_name' => 'auto_cancel',
            'due_at' => DateTime::now()->addHours(1),
            'processed' => false,
            'created' => DateTime::now(),
        ]));

        $this->post([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'cancel',
            $timeout->id,
        ]);

        $this->assertRedirect([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'index',
        ]);
        $this->assertFlashMessage('Timeout cancelled successfully.');

        // Verify timeout is marked as processed
        $updated = $timeoutsTable->get($timeout->id);
        $this->assertTrue($updated->processed);
    }

    /**
     * Test cancel action with already processed timeout.
     */
    public function testCancelAlreadyProcessed(): void
    {
        $this->enableRetainFlashMessages();

        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $timeout = $timeoutsTable->save($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'current_state' => 'pending',
            'transition_name' => 'auto_cancel',
            'due_at' => DateTime::now()->subHours(1),
            'processed' => true,
            'created' => DateTime::now()->subHours(2),
        ]));

        $this->post([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'cancel',
            $timeout->id,
        ]);

        $this->assertRedirect([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'index',
        ]);
        $this->assertFlashMessage('This timeout has already been processed.');
    }

    /**
     * Test cancel action with non-existent timeout.
     */
    public function testCancelNonExistentTimeout(): void
    {
        $this->disableErrorHandlerMiddleware();
        $this->expectException(RecordNotFoundException::class);

        $this->post([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'cancel',
            9999,
        ]);
    }

    public function testBulkCancelMarksSelectedTimeoutsAsProcessed(): void
    {
        $this->enableRetainFlashMessages();

        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $timeoutA = $timeoutsTable->saveOrFail($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'current_state' => 'pending',
            'transition_name' => 'pay',
            'due_at' => DateTime::now()->addHours(1),
            'processed' => false,
        ]));
        $timeoutB = $timeoutsTable->saveOrFail($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '456',
            'current_state' => 'pending',
            'transition_name' => 'pay',
            'due_at' => DateTime::now()->addHours(2),
            'processed' => false,
        ]));

        $this->post([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'bulkCancel',
        ], [
            'timeout_ids' => [$timeoutA->id, $timeoutB->id],
        ]);

        $this->assertRedirect([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'index',
        ]);
        $this->assertFlashMessage('Cancelled 2 timeout(s).');
        $this->assertTrue($timeoutsTable->get($timeoutA->id)->processed);
        $this->assertTrue($timeoutsTable->get($timeoutB->id)->processed);
    }

    public function testBulkExecuteRunsSelectedTimeoutsAndLogsActor(): void
    {
        $this->enableRetainFlashMessages();
        $this->session([
            'Auth' => [
                'User' => [
                    'id' => 'legacy-admin',
                ],
            ],
        ]);

        $orderA = $this->createOrder('pending');
        $orderB = $this->createOrder('pending');
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $timeoutA = $timeoutsTable->saveOrFail($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => (string)$orderA,
            'current_state' => 'pending',
            'transition_name' => 'pay',
            'due_at' => DateTime::now()->subMinutes(1),
            'processed' => false,
        ]));
        $timeoutB = $timeoutsTable->saveOrFail($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => (string)$orderB,
            'current_state' => 'pending',
            'transition_name' => 'pay',
            'due_at' => DateTime::now()->subMinutes(2),
            'processed' => false,
        ]));

        $this->post([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'bulkExecute',
        ], [
            'timeout_ids' => [$timeoutA->id, $timeoutB->id],
        ]);

        $this->assertRedirect([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'index',
        ]);
        $this->assertFlashMessage('Processed 2 timeout(s): 2 executed.');
        $this->assertSame('paid', $this->fetchTable('Orders')->get($orderA)->get('state'));
        $this->assertSame('paid', $this->fetchTable('Orders')->get($orderB)->get('state'));
        $this->assertTrue($timeoutsTable->get($timeoutA->id)->processed);
        $this->assertTrue($timeoutsTable->get($timeoutB->id)->processed);

        $transitions = $this->fetchTable('Workflow.WorkflowTransitions')->find()->all()->toArray();
        $this->assertCount(2, $transitions);
        $this->assertSame('legacy-admin', $transitions[0]->user_id);
        $this->assertTrue($transitions[0]->isAdminAction());
    }

    public function testExecuteDueRunsOnlyDueTimeouts(): void
    {
        $this->enableRetainFlashMessages();

        $dueOrder = $this->createOrder('pending');
        $futureOrder = $this->createOrder('pending');
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $dueTimeout = $timeoutsTable->saveOrFail($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => (string)$dueOrder,
            'current_state' => 'pending',
            'transition_name' => 'pay',
            'due_at' => DateTime::now()->subMinutes(1),
            'processed' => false,
        ]));
        $futureTimeout = $timeoutsTable->saveOrFail($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => (string)$futureOrder,
            'current_state' => 'pending',
            'transition_name' => 'pay',
            'due_at' => DateTime::now()->addHours(1),
            'processed' => false,
        ]));

        $this->post([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'executeDue',
        ]);

        $this->assertRedirect([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Timeouts',
            'action' => 'index',
        ]);
        $this->assertFlashMessage('Processed 1 timeout(s): 1 executed.');
        $this->assertTrue($timeoutsTable->get($dueTimeout->id)->processed);
        $this->assertFalse($timeoutsTable->get($futureTimeout->id)->processed);
        $this->assertSame('paid', $this->fetchTable('Orders')->get($dueOrder)->get('state'));
        $this->assertSame('pending', $this->fetchTable('Orders')->get($futureOrder)->get('state'));
    }
}
