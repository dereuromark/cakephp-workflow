<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Controller\Admin;

use Cake\Core\Configure;
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
            'model' => 'Orders',
            'foreign_key' => '123',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'status' => 'success',
            'created' => DateTime::now(),
        ]));

        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'payment',
            'model' => 'Payments',
            'foreign_key' => '456',
            'transition_name' => 'process',
            'from_state' => 'pending',
            'to_state' => 'processed',
            'status' => 'success',
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
     * Test index action filters by foreign_key.
     */
    public function testIndexFiltersEntityId(): void
    {
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');

        // Add transitions for different entities
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

        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'model' => 'Orders',
            'foreign_key' => '456',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'status' => 'success',
            'created' => DateTime::now(),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Transitions',
            'action' => 'index',
            '?' => ['foreign_key' => '123'],
        ]);

        $this->assertResponseOk();

        $transitions = $this->viewVariable('transitions');
        $this->assertCount(1, $transitions);
        $this->assertSame(123, $transitions->items()->first()->foreign_key);
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
            'model' => 'Orders',
            'foreign_key' => '123',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'status' => 'success',
            'created' => DateTime::now()->subHours(1),
        ]));

        // Add newer transition
        $transitionsTable->save($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'model' => 'Orders',
            'foreign_key' => '456',
            'transition_name' => 'ship',
            'from_state' => 'paid',
            'to_state' => 'shipped',
            'status' => 'success',
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
        $this->assertSame(456, $transitions[0]->foreign_key);
        $this->assertSame(123, $transitions[1]->foreign_key);
    }

    public function testIndexFiltersByStatusAndActor(): void
    {
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
        $transitionsTable->saveManyOrFail([
            $transitionsTable->newEntity([
                'workflow_name' => 'order',
                'model' => 'Orders',
                'foreign_key' => '123',
                'transition_name' => 'pay',
                'from_state' => 'pending',
                'to_state' => 'paid',
                'status' => 'success',
                'user_id' => 'admin-1',
                'created' => DateTime::now(),
            ]),
            $transitionsTable->newEntity([
                'workflow_name' => 'order',
                'model' => 'Orders',
                'foreign_key' => '456',
                'transition_name' => 'pay',
                'from_state' => 'pending',
                'to_state' => 'paid',
                'status' => 'blocked',
                'user_id' => 'admin-2',
                'created' => DateTime::now(),
            ]),
        ]);

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Transitions',
            'action' => 'index',
            '?' => [
                'status' => 'blocked',
                'user_id' => 'admin-2',
            ],
        ]);

        $this->assertResponseOk();

        $transitions = $this->viewVariable('transitions');
        $this->assertCount(1, $transitions);
        $this->assertSame('blocked', $transitions->items()->first()->status);
        $this->assertSame('admin-2', $transitions->items()->first()->user_id);
    }

    public function testIndexFiltersByAdminActionAndDateRange(): void
    {
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
        $todayTransition = $transitionsTable->newEntity([
            'workflow_name' => 'order',
            'model' => 'Orders',
            'foreign_key' => '123',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'status' => 'success',
            'context' => ['admin_action' => true],
            'created' => DateTime::now(),
        ]);
        $transitionsTable->saveManyOrFail([
            $todayTransition,
            $transitionsTable->newEntity([
                'workflow_name' => 'order',
                'model' => 'Orders',
                'foreign_key' => '456',
                'transition_name' => 'ship',
                'from_state' => 'paid',
                'to_state' => 'shipped',
                'status' => 'success',
                'context' => ['admin_action' => false],
                'created' => DateTime::now()->subDays(5),
            ]),
        ]);
        $savedDate = $transitionsTable->getConnection()
            ->execute('SELECT substr(created, 1, 10) AS created_date FROM workflow_transitions WHERE foreign_key = :id', ['id' => '123'])
            ->fetch('assoc')['created_date'];

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Transitions',
            'action' => 'index',
            '?' => [
                'admin_action' => 'yes',
                'created_from' => $savedDate,
                'created_to' => $savedDate,
            ],
        ]);

        $this->assertResponseOk();

        $transitions = $this->viewVariable('transitions');
        $this->assertCount(1, $transitions);
        $this->assertTrue($transitions->items()->first()->isAdminAction());
    }

    public function testViewDecodesLegacyStringContextAndUsesActorResolver(): void
    {
        Configure::write('Workflow.adminActorResolver', function (string $userId): array {
            return [
                'label' => strtoupper($userId),
                'url' => '/users/view/' . $userId,
            ];
        });

        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
        $transition = $transitionsTable->saveOrFail($transitionsTable->newEntity([
            'workflow_name' => 'order',
            'model' => 'Orders',
            'foreign_key' => '123',
            'transition_name' => 'pay',
            'from_state' => 'pending',
            'to_state' => 'paid',
            'status' => 'success',
            'user_id' => 'admin-1',
            'context' => json_encode([
                'admin_action' => true,
                'client_ip' => '127.0.0.1',
            ]),
            'created' => DateTime::now(),
        ]));

        $this->get([
            'prefix' => 'Admin',
            'plugin' => 'Workflow',
            'controller' => 'Transitions',
            'action' => 'view',
            $transition->id,
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('ADMIN-1');
        $this->assertResponseContains('127.0.0.1');
        $this->assertResponseContains('Admin action');
    }
}
