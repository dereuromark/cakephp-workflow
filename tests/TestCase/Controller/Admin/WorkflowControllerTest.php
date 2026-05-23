<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Controller\Admin;

use Cake\I18n\DateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Workflow\Test\TestCase\Controller\IntegrationTestCase;

/**
 * @uses \Workflow\Controller\Admin\WorkflowController
 */
#[AllowMockObjectsWithoutExpectations]
class WorkflowControllerTest extends IntegrationTestCase
{
    /**
     * Test index action renders successfully.
     */
    public function testIndex(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflow',
            'action' => 'index',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('order');
        $this->assertResponseContains('payment');
    }

    /**
     * Test index action shows workflow stats.
     */
    public function testIndexShowsWorkflowStats(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflow',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        // Check that view variables are set
        $workflows = $this->viewVariable('workflows');
        $this->assertIsArray($workflows);
        $this->assertCount(2, $workflows);

        $totalActiveItems = $this->viewVariable('totalActiveItems');
        $this->assertIsInt($totalActiveItems);

        $transitionsToday = $this->viewVariable('transitionsToday');
        $this->assertIsInt($transitionsToday);
    }

    /**
     * Test index action shows recent transitions.
     */
    public function testIndexShowsRecentTransitions(): void
    {
        // Add some test transitions
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'model' => 'Orders',
            'foreign_key' => '123',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'status' => 'success',
            'created' => DateTime::now(),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflow',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $recentTransitions = $this->viewVariable('recentTransitions');
        $this->assertIsArray($recentTransitions);
        $this->assertCount(1, $recentTransitions);
    }

    /**
     * Test index action shows pending timeouts.
     */
    public function testIndexShowsPendingTimeouts(): void
    {
        // Add a test timeout
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $timeoutsTable->save($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'model' => 'Orders',
            'foreign_key' => '123',
            'current_state' => 'pending',
            'transition_name' => 'auto_cancel',
            'due_at' => DateTime::now()->addHours(1),
            'processed' => false,
            'created' => DateTime::now(),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflow',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $pendingTimeouts = $this->viewVariable('pendingTimeouts');
        $this->assertIsArray($pendingTimeouts);
        $this->assertCount(1, $pendingTimeouts);
    }

    /**
     * Test index action counts transitions today.
     */
    public function testIndexCountsTransitionsToday(): void
    {
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');

        // Add today's transition
        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'model' => 'Orders',
            'foreign_key' => '123',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'status' => 'success',
            'created' => DateTime::now(),
        ]));

        // Add yesterday's transition
        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'model' => 'Orders',
            'foreign_key' => '456',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'status' => 'success',
            'created' => DateTime::now()->subDays(1),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflow',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $transitionsToday = $this->viewVariable('transitionsToday');
        $this->assertSame(1, $transitionsToday);
    }
}
