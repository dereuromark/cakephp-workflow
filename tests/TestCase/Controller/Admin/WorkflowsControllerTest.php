<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Controller\Admin;

use Cake\I18n\DateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Workflow\Exception\WorkflowException;
use Workflow\Test\TestCase\Controller\IntegrationTestCase;

/**
 * @uses \Workflow\Controller\Admin\WorkflowsController
 */
#[AllowMockObjectsWithoutExpectations]
class WorkflowsControllerTest extends IntegrationTestCase
{
    /**
     * Test index action renders successfully.
     */
    public function testIndex(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'index',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('order');
        $this->assertResponseContains('payment');
    }

    /**
     * Test index action sets correct view variables.
     */
    public function testIndexSetsViewVariables(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $workflows = $this->viewVariable('workflows');
        $this->assertIsArray($workflows);
        $this->assertCount(2, $workflows);

        // Check workflow data structure
        $orderWorkflow = array_filter($workflows, fn ($w) => $w['name'] === 'order');
        $orderWorkflow = reset($orderWorkflow);
        $this->assertNotFalse($orderWorkflow);
        $this->assertArrayHasKey('definition', $orderWorkflow);
        $this->assertArrayHasKey('stateCount', $orderWorkflow);
        $this->assertArrayHasKey('transitionCount', $orderWorkflow);
        $this->assertSame(5, $orderWorkflow['stateCount']);
        $this->assertSame(4, $orderWorkflow['transitionCount']);
    }

    /**
     * Test view action for a valid workflow.
     */
    public function testView(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'view',
            'order',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('order');
    }

    /**
     * Test view action sets correct view variables.
     */
    public function testViewSetsViewVariables(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'view',
            'order',
        ]);

        $this->assertResponseOk();

        $definition = $this->viewVariable('definition');
        $this->assertNotNull($definition);
        $this->assertSame('order', $definition->getName());

        $stateCounts = $this->viewVariable('stateCounts');
        $this->assertIsArray($stateCounts);

        $totalActive = $this->viewVariable('totalActive');
        $this->assertIsInt($totalActive);

        $recentTransitions = $this->viewVariable('recentTransitions');
        $this->assertIsArray($recentTransitions);

        $transitionsToday = $this->viewVariable('transitionsToday');
        $this->assertIsInt($transitionsToday);

        $pendingTimeouts = $this->viewVariable('pendingTimeouts');
        $this->assertIsArray($pendingTimeouts);
    }

    /**
     * Test view action shows recent transitions for the workflow.
     */
    public function testViewShowsRecentTransitions(): void
    {
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');

        // Add a transition for 'order' workflow
        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'created' => DateTime::now(),
        ]));

        // Add a transition for 'payment' workflow (should not appear)
        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'payment',
            'entity_table' => 'Payments',
            'entity_id' => '456',
            'transition_name' => 'process',
            'from_state' => 'pending',
            'to_state' => 'processed',
            'created' => DateTime::now(),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'view',
            'order',
        ]);

        $this->assertResponseOk();

        $recentTransitions = $this->viewVariable('recentTransitions');
        $this->assertCount(1, $recentTransitions);
        $this->assertSame('order', $recentTransitions[0]->workflow_name);
    }

    /**
     * Test view action shows pending timeouts for the workflow.
     */
    public function testViewShowsPendingTimeouts(): void
    {
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');

        // Add a pending timeout for 'order' workflow
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

        // Add a processed timeout (should not appear)
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
            'controller' => 'Workflows',
            'action' => 'view',
            'order',
        ]);

        $this->assertResponseOk();

        $pendingTimeouts = $this->viewVariable('pendingTimeouts');
        $this->assertCount(1, $pendingTimeouts);
        $this->assertFalse($pendingTimeouts[0]->processed);
    }

    /**
     * Test view action counts today's transitions.
     */
    public function testViewCountsTransitionsToday(): void
    {
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');

        // Add today's transition
        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'created' => DateTime::now(),
        ]));

        // Add yesterday's transition
        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '456',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'created' => DateTime::now()->subDays(1),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'view',
            'order',
        ]);

        $this->assertResponseOk();

        $transitionsToday = $this->viewVariable('transitionsToday');
        $this->assertSame(1, $transitionsToday);
    }

    /**
     * Test view action with non-existent workflow throws exception.
     */
    public function testViewNonExistentWorkflow(): void
    {
        $this->disableErrorHandlerMiddleware();
        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Workflow 'nonexistent' not found");

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'view',
            'nonexistent',
        ]);
    }

    /**
     * Test matrix action for a valid workflow.
     */
    public function testMatrix(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'matrix',
            'order',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('order');
        $this->assertResponseContains('Matrix');
    }

    /**
     * Test matrix action sets correct view variables.
     */
    public function testMatrixSetsViewVariables(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'matrix',
            'order',
        ]);

        $this->assertResponseOk();

        $definition = $this->viewVariable('definition');
        $this->assertNotNull($definition);
        $this->assertSame('order', $definition->getName());

        $matrix = $this->viewVariable('matrix');
        $this->assertIsArray($matrix);
        // Should have entries for each state in the workflow
        $this->assertArrayHasKey('pending', $matrix);
        $this->assertArrayHasKey('paid', $matrix);

        $timeBuckets = $this->viewVariable('timeBuckets');
        $this->assertIsArray($timeBuckets);
        $this->assertArrayHasKey('< 1 hour', $timeBuckets);
        $this->assertArrayHasKey('1-4 hours', $timeBuckets);
        $this->assertArrayHasKey('4-24 hours', $timeBuckets);
        $this->assertArrayHasKey('1-7 days', $timeBuckets);
        $this->assertArrayHasKey('> 7 days', $timeBuckets);

        $totals = $this->viewVariable('totals');
        $this->assertIsArray($totals);

        $stateTotals = $this->viewVariable('stateTotals');
        $this->assertIsArray($stateTotals);
    }

    /**
     * Test matrix action with non-existent workflow throws exception.
     */
    public function testMatrixNonExistentWorkflow(): void
    {
        $this->disableErrorHandlerMiddleware();
        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Workflow 'nonexistent' not found");

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Workflows',
            'action' => 'matrix',
            'nonexistent',
        ]);
    }
}
