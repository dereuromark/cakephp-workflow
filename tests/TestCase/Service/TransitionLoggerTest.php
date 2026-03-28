<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Service;

use Cake\ORM\Entity;
use Workflow\Engine\TransitionResult;
use Workflow\Service\TransitionLogger;
use Workflow\Test\TestCase\DatabaseTestCase;

class TransitionLoggerTest extends DatabaseTestCase
{
    private TransitionLogger $logger;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();
        $this->logger = new TransitionLogger();
    }

    public function testLogSuccessfulTransition(): void
    {
        $entity = new Entity(['id' => '123']);
        $result = TransitionResult::success('pending', 'paid');

        $this->logger->log(
            'order',
            'Orders',
            $entity,
            $result,
            'pay',
            ['user_id' => 'user-1'],
        );

        $table = $this->fetchTable('Workflow.WorkflowTransitions');
        $transition = $table->find()->first();

        $this->assertNotNull($transition);
        $this->assertSame('order', $transition->workflow_name);
        $this->assertSame('Orders', $transition->entity_table);
        $this->assertSame('123', $transition->entity_id);
        $this->assertSame('pay', $transition->transition_name);
        $this->assertSame('pending', $transition->from_state);
        $this->assertSame('paid', $transition->to_state);
        $this->assertSame('user-1', $transition->user_id);
    }

    public function testLogDoesNotLogFailedTransition(): void
    {
        $entity = new Entity(['id' => '123']);
        $result = TransitionResult::blocked('pending', ['guard' => 'Permission denied']);

        $this->logger->log(
            'order',
            'Orders',
            $entity,
            $result,
            'pay',
        );

        $table = $this->fetchTable('Workflow.WorkflowTransitions');
        $count = $table->find()->count();

        $this->assertSame(0, $count);
    }

    public function testLogWithReason(): void
    {
        $entity = new Entity(['id' => '123']);
        $result = TransitionResult::success('pending', 'cancelled');

        $this->logger->log(
            'order',
            'Orders',
            $entity,
            $result,
            'cancel',
            ['reason' => 'Customer requested cancellation'],
        );

        $table = $this->fetchTable('Workflow.WorkflowTransitions');
        $transition = $table->find()->first();

        $this->assertSame('Customer requested cancellation', $transition->reason);
    }

    public function testLogWithContext(): void
    {
        $entity = new Entity(['id' => '123']);
        $result = TransitionResult::success('pending', 'paid');

        $context = [
            'user_id' => 'user-1',
            'ip_address' => '192.168.1.1',
            'metadata' => ['key' => 'value'],
        ];

        $this->logger->log(
            'order',
            'Orders',
            $entity,
            $result,
            'pay',
            $context,
        );

        $table = $this->fetchTable('Workflow.WorkflowTransitions');
        $transition = $table->find()->first();

        $this->assertNotNull($transition->context);
        $decoded = json_decode($transition->context, true);
        $this->assertSame('192.168.1.1', $decoded['ip_address']);
        $this->assertSame(['key' => 'value'], $decoded['metadata']);
    }

    public function testLogWithWorkflowVersion(): void
    {
        $entity = new Entity(['id' => '123']);
        $result = TransitionResult::success('pending', 'paid');

        $this->logger->log(
            'order',
            'Orders',
            $entity,
            $result,
            'pay',
            [],
            '1.0.0',
        );

        $table = $this->fetchTable('Workflow.WorkflowTransitions');
        $transition = $table->find()->first();

        $this->assertSame('1.0.0', $transition->workflow_version);
    }

    public function testGetHistoryReturnsTransitions(): void
    {
        // Log multiple transitions
        $entity = new Entity(['id' => '123']);

        $result1 = TransitionResult::success('pending', 'paid');
        $this->logger->log('order', 'Orders', $entity, $result1, 'pay');

        $result2 = TransitionResult::success('paid', 'shipped');
        $this->logger->log('order', 'Orders', $entity, $result2, 'ship');

        $history = $this->logger->getHistory('order', 'Orders', '123');

        $this->assertCount(2, $history);
        // Check both transitions are in history (order may vary if created same second)
        $transitionNames = array_map(fn ($t) => $t->transition_name, $history);
        $this->assertContains('pay', $transitionNames);
        $this->assertContains('ship', $transitionNames);
    }

    public function testGetHistoryReturnsEmptyArrayForNoHistory(): void
    {
        $history = $this->logger->getHistory('order', 'Orders', '999');

        $this->assertEmpty($history);
    }

    public function testGetHistoryFiltersCorrectly(): void
    {
        // Log transitions for different entities
        $entity1 = new Entity(['id' => '123']);
        $entity2 = new Entity(['id' => '456']);

        $result = TransitionResult::success('pending', 'paid');

        $this->logger->log('order', 'Orders', $entity1, $result, 'pay');
        $this->logger->log('order', 'Orders', $entity2, $result, 'pay');
        $this->logger->log('payment', 'Payments', $entity1, $result, 'process');

        $history = $this->logger->getHistory('order', 'Orders', '123');

        $this->assertCount(1, $history);
        $this->assertSame('123', $history[0]->entity_id);
        $this->assertSame('order', $history[0]->workflow_name);
    }

    public function testLogWithEmptyContext(): void
    {
        $entity = new Entity(['id' => '123']);
        $result = TransitionResult::success('pending', 'paid');

        $this->logger->log(
            'order',
            'Orders',
            $entity,
            $result,
            'pay',
            [],
        );

        $table = $this->fetchTable('Workflow.WorkflowTransitions');
        $transition = $table->find()->first();

        $this->assertNull($transition->context);
    }
}
