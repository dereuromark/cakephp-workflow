<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Service;

use Cake\ORM\Entity;
use RuntimeException;
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
        $this->assertSame('Orders', $transition->model);
        $this->assertSame(123, $transition->foreign_key);
        $this->assertSame('pay', $transition->transition_name);
        $this->assertSame('pending', $transition->from_state);
        $this->assertSame('paid', $transition->to_state);
        $this->assertSame('success', $transition->status);
        $this->assertSame('user-1', $transition->user_id);
        $this->assertTrue($transition->isSuccess());
    }

    public function testLogBlockedTransition(): void
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
        $transition = $table->find()->first();

        $this->assertNotNull($transition);
        $this->assertSame('blocked', $transition->status);
        $this->assertSame('pending', $transition->from_state);
        $this->assertSame('pending', $transition->to_state); // Stays in same state
        $this->assertTrue($transition->isBlocked());
        $this->assertSame(['guard' => 'Permission denied'], $transition->getBlockedBy());
    }

    public function testLogLockedTransition(): void
    {
        $entity = new Entity(['id' => '123']);
        $result = TransitionResult::locked('pending');

        $this->logger->log(
            'order',
            'Orders',
            $entity,
            $result,
            'pay',
        );

        $table = $this->fetchTable('Workflow.WorkflowTransitions');
        $transition = $table->find()->first();

        $this->assertNotNull($transition);
        $this->assertSame('locked', $transition->status);
        $this->assertTrue($transition->isLocked());
    }

    public function testLogErrorTransition(): void
    {
        $entity = new Entity(['id' => '123']);
        $exception = new RuntimeException('Command failed');
        $result = TransitionResult::error('pending', $exception);

        $this->logger->log(
            'order',
            'Orders',
            $entity,
            $result,
            'pay',
        );

        $table = $this->fetchTable('Workflow.WorkflowTransitions');
        $transition = $table->find()->first();

        $this->assertNotNull($transition);
        $this->assertSame('error', $transition->status);
        $this->assertTrue($transition->isError());

        $errorDetails = $transition->getErrorDetails();
        $this->assertNotNull($errorDetails);
        $this->assertSame('Command failed', $errorDetails['message']);
        $this->assertSame(RuntimeException::class, $errorDetails['class']);
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

        // Context is now automatically decoded from JSON
        $this->assertNotNull($transition->context);
        $this->assertIsArray($transition->context);
        $this->assertSame('192.168.1.1', $transition->context['ip_address']);
        $this->assertSame(['key' => 'value'], $transition->context['metadata']);
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
        $this->assertSame(123, $history[0]->foreign_key);
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

        // Empty context with no runtime metadata results in null
        $this->assertNull($transition->context);
    }

    public function testGetHistorySuccessOnly(): void
    {
        $entity = new Entity(['id' => '123']);

        // Log success
        $result1 = TransitionResult::success('pending', 'paid');
        $this->logger->log('order', 'Orders', $entity, $result1, 'pay');

        // Log blocked
        $result2 = TransitionResult::blocked('paid', ['guard' => 'Not allowed']);
        $this->logger->log('order', 'Orders', $entity, $result2, 'ship');

        // Log another success
        $result3 = TransitionResult::success('paid', 'shipped');
        $this->logger->log('order', 'Orders', $entity, $result3, 'ship');

        // Get all history
        $allHistory = $this->logger->getHistory('order', 'Orders', '123');
        $this->assertCount(3, $allHistory);

        // Get success only
        $successHistory = $this->logger->getHistory('order', 'Orders', '123', successOnly: true);
        $this->assertCount(2, $successHistory);
        foreach ($successHistory as $t) {
            $this->assertTrue($t->isSuccess());
        }
    }

    public function testLogWithRuntimeMetadata(): void
    {
        $entity = new Entity(['id' => '123']);

        // Create result with runtime metadata via constructor
        $result = TransitionResult::success(
            'pending',
            'paid',
            guardsEvaluated: ['checkBalance', 'checkInventory'],
            commandsExecuted: ['sendEmail'],
            usedLock: true,
        );

        $this->logger->log(
            'order',
            'Orders',
            $entity,
            $result,
            'pay',
        );

        $table = $this->fetchTable('Workflow.WorkflowTransitions');
        $transition = $table->find()->first();

        $this->assertNotNull($transition);
        $runtime = $transition->getRuntime();
        $this->assertNotNull($runtime);
        $this->assertContains('checkBalance', $transition->getGuardsEvaluated());
        $this->assertContains('checkInventory', $transition->getGuardsEvaluated());
        $this->assertContains('sendEmail', $transition->getCommandsExecuted());
        $this->assertTrue($transition->usedLock());
    }
}
