<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Controller\Admin;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Workflow\Test\TestCase\Controller\IntegrationTestCase;

/**
 * @uses \Workflow\Controller\Admin\OrphansController
 */
#[AllowMockObjectsWithoutExpectations]
class OrphansControllerTest extends IntegrationTestCase
{
    /**
     * Test index action renders successfully.
     */
    public function testIndex(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Orphans',
            'action' => 'index',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Orphaned Items');
    }

    /**
     * Test index action sets correct view variables.
     */
    public function testIndexSetsViewVariables(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Orphans',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $orphans = $this->viewVariable('orphans');
        $this->assertIsArray($orphans);

        $orphanCounts = $this->viewVariable('orphanCounts');
        $this->assertIsArray($orphanCounts);

        $totalOrphans = $this->viewVariable('totalOrphans');
        $this->assertIsInt($totalOrphans);

        $workflowNames = $this->viewVariable('workflowNames');
        $this->assertIsArray($workflowNames);
        $this->assertContains('order', $workflowNames);
        $this->assertContains('payment', $workflowNames);
    }

    /**
     * Test index action shows no orphans when all items have valid states.
     */
    public function testIndexShowsNoOrphansWhenAllValid(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Orphans',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $totalOrphans = $this->viewVariable('totalOrphans');
        $this->assertSame(0, $totalOrphans);

        $this->assertResponseContains('No orphaned items found');
    }

    /**
     * Test index action can filter by workflow.
     */
    public function testIndexFiltersWorkflow(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Orphans',
            'action' => 'index',
            '?' => ['workflow' => 'order'],
        ]);

        $this->assertResponseOk();

        $selectedWorkflow = $this->viewVariable('selectedWorkflow');
        $this->assertSame('order', $selectedWorkflow);

        // Only 'order' workflow should be in counts
        $orphanCounts = $this->viewVariable('orphanCounts');
        $this->assertCount(1, $orphanCounts);
        $this->assertArrayHasKey('order', $orphanCounts);
    }

    /**
     * Test index action provides workflow names for filter.
     */
    public function testIndexProvidesWorkflowNames(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Orphans',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $workflowNames = $this->viewVariable('workflowNames');
        $this->assertContains('order', $workflowNames);
        $this->assertContains('payment', $workflowNames);
    }

    public function testFixGetRendersForm(): void
    {
        $orderId = $this->createOrder('unknown');

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Orphans',
            'action' => 'fix',
            'order',
            (string)$orderId,
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Fix Orphaned Entity');
        $this->assertResponseContains('unknown');
    }

    public function testFixPostLogsLegacySessionActor(): void
    {
        $this->enableRetainFlashMessages();
        $this->session([
            'Auth' => [
                'User' => [
                    'id' => 'legacy-admin',
                ],
            ],
        ]);

        $orderId = $this->createOrder('unknown');

        $this->post([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Orphans',
            'action' => 'fix',
            'order',
            (string)$orderId,
        ], [
            'new_state' => 'pending',
            'reason' => 'Repair orphan',
        ]);

        $this->assertRedirect([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Orphans',
            'action' => 'index',
            '?' => ['workflow' => 'order'],
        ]);
        $this->assertFlashMessage(sprintf(
            'Entity #%d state changed from "unknown" to "pending".',
            $orderId,
        ));

        $order = $this->fetchTable('Orders')->get($orderId);
        $this->assertSame('pending', $order->get('state'));

        $transition = $this->fetchTable('Workflow.WorkflowTransitions')->find()->firstOrFail();
        $this->assertSame('legacy-admin', $transition->user_id);
        $this->assertSame('success', $transition->status);
        $this->assertIsArray($transition->context);
        $this->assertSame('orphan_fix', $transition->context['type']);
        $this->assertTrue($transition->context['admin_action']);
        $this->assertSame('legacy-admin', $transition->context['user_id']);
    }
}
