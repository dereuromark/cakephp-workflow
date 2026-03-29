<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Controller\Admin;

use Cake\I18n\DateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Workflow\Test\TestCase\Controller\IntegrationTestCase;

/**
 * @uses \Workflow\Controller\Admin\TransitionsController
 */
#[AllowMockObjectsWithoutExpectations]
class TransitionsControllerTest extends IntegrationTestCase
{
    /**
     * Test index action renders successfully.
     */
    public function testIndex(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Transitions',
            'action' => 'index',
        ]);

        $this->assertResponseOk();
    }

    /**
     * Test index action shows transitions.
     */
    public function testIndexShowsTransitions(): void
    {
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'created' => DateTime::now(),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Transitions',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $transitions = $this->viewVariable('transitions');
        $this->assertNotEmpty($transitions);
    }

    /**
     * Test index action filters by workflow.
     */
    public function testIndexFiltersWorkflow(): void
    {
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');

        // Add transitions for both workflows
        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'created' => DateTime::now(),
        ]));

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
            'controller' => 'Transitions',
            'action' => 'index',
            '?' => ['workflow' => 'order'],
        ]);

        $this->assertResponseOk();

        $transitions = $this->viewVariable('transitions');
        $this->assertCount(1, $transitions);
        $this->assertSame('order', $transitions->items()->first()->workflow_name);
    }

    /**
     * Test index action filters by entity_id.
     */
    public function testIndexFiltersEntityId(): void
    {
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');

        // Add transitions for different entities
        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'created' => DateTime::now(),
        ]));

        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '456',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'created' => DateTime::now(),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Transitions',
            'action' => 'index',
            '?' => ['entity_id' => '123'],
        ]);

        $this->assertResponseOk();

        $transitions = $this->viewVariable('transitions');
        $this->assertCount(1, $transitions);
        $this->assertSame('123', $transitions->items()->first()->entity_id);
    }

    /**
     * Test index action provides workflow names for filter.
     */
    public function testIndexProvidesWorkflowNames(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Transitions',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $workflowNames = $this->viewVariable('workflowNames');
        $this->assertIsArray($workflowNames);
        $this->assertContains('order', $workflowNames);
        $this->assertContains('payment', $workflowNames);
    }

    /**
     * Test index action handles empty state.
     */
    public function testIndexEmptyState(): void
    {
        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Transitions',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $transitions = $this->viewVariable('transitions');
        $this->assertEmpty($transitions->toArray());
    }

    /**
     * Test index action orders by created DESC.
     */
    public function testIndexOrdersDescending(): void
    {
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');

        // Add older transition first
        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'created' => DateTime::now()->subHours(1),
        ]));

        // Add newer transition
        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '456',
            'transition_name' => 'ship',
            'from_state' => 'paid',
            'to_state' => 'shipped',
            'created' => DateTime::now(),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Transitions',
            'action' => 'index',
        ]);

        $this->assertResponseOk();

        $transitions = $this->viewVariable('transitions')->toArray();
        $this->assertCount(2, $transitions);
        // Newest first
        $this->assertSame('456', $transitions[0]->entity_id);
        $this->assertSame('123', $transitions[1]->entity_id);
    }
}
