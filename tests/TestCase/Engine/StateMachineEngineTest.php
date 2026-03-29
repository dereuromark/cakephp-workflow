<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Engine;

use Cake\Event\EventManager;
use Cake\ORM\Entity;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Engine\StateMachineEngine;
use Workflow\Exception\WorkflowException;

class StateMachineEngineTest extends TestCase
{
    private StateMachineEngine $engine;

    private Definition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new StateMachineEngine(new EventManager());
        $this->definition = $this->createTestDefinition();
    }

    private function createTestDefinition(): Definition
    {
        return new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
                new State('shipped'),
                new State('completed', final: true),
                new State('cancelled', final: true, failed: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
                new Transition('ship', ['paid'], 'shipped'),
                new Transition('complete', ['shipped'], 'completed'),
                new Transition('cancel', ['pending', 'paid'], 'cancelled'),
            ],
        );
    }

    public function testGetCurrentStateReturnsInitialForNewEntity(): void
    {
        $entity = new Entity(['state' => null]);
        $state = $this->engine->getCurrentState($this->definition, $entity);

        $this->assertSame('pending', $state);
    }

    public function testGetCurrentStateReturnsEntityState(): void
    {
        $entity = new Entity(['state' => 'paid']);
        $state = $this->engine->getCurrentState($this->definition, $entity);

        $this->assertSame('paid', $state);
    }

    public function testCanReturnsTrueForValidTransition(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $can = $this->engine->can($this->definition, $entity, 'pay');

        $this->assertTrue($can);
    }

    public function testCanReturnsFalseForInvalidTransition(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $can = $this->engine->can($this->definition, $entity, 'ship');

        $this->assertFalse($can);
    }

    public function testCanReturnsFalseForNonexistentTransition(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $can = $this->engine->can($this->definition, $entity, 'nonexistent');

        $this->assertFalse($can);
    }

    public function testCanReturnsFalseFromFinalState(): void
    {
        $entity = new Entity(['state' => 'completed']);
        $can = $this->engine->can($this->definition, $entity, 'cancel');

        $this->assertFalse($can);
    }

    public function testApplySuccessfulTransition(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $result = $this->engine->apply($this->definition, $entity, 'pay');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('pending', $result->getFromState());
        $this->assertSame('paid', $result->getToState());
        $this->assertSame('paid', $entity->get('state'));
    }

    public function testApplyBlockedFromFinalState(): void
    {
        $entity = new Entity(['state' => 'completed']);
        $result = $this->engine->apply($this->definition, $entity, 'cancel');

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isBlocked());
        $this->assertArrayHasKey('state', $result->getBlockedBy());
    }

    public function testApplyBlockedForNonexistentTransition(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $result = $this->engine->apply($this->definition, $entity, 'nonexistent');

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isBlocked());
        $this->assertArrayHasKey('transition', $result->getBlockedBy());
    }

    public function testApplyBlockedForInvalidFromState(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $result = $this->engine->apply($this->definition, $entity, 'complete');

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isBlocked());
    }

    public function testGetAvailableTransitions(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $transitions = $this->engine->getAvailableTransitions($this->definition, $entity);

        $this->assertContains('pay', $transitions);
        $this->assertContains('cancel', $transitions);
        $this->assertNotContains('ship', $transitions);
    }

    public function testGetAvailableTransitionsFiltersGuardBlockedTransitions(): void
    {
        $this->engine->addGuard('canPay', fn ($entity, $context) => false);

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', guards: ['canPay']),
            ],
        );

        $entity = new Entity(['state' => 'pending']);

        $this->assertSame([], $this->engine->getAvailableTransitions($definition, $entity));
    }

    public function testGetAvailableTransitionsFromFinalState(): void
    {
        $entity = new Entity(['state' => 'completed']);
        $transitions = $this->engine->getAvailableTransitions($this->definition, $entity);

        $this->assertEmpty($transitions);
    }

    public function testGuardBlocksTransition(): void
    {
        $this->engine->addGuard('checkPayment', function ($entity, $context) {
            return 'Payment not received';
        });

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', guards: ['checkPayment']),
            ],
        );

        $entity = new Entity(['state' => 'pending']);
        $result = $this->engine->apply($definition, $entity, 'pay');

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isBlocked());
        $this->assertArrayHasKey('checkPayment', $result->getBlockedBy());
        $this->assertSame('Payment not received', $result->getBlockedBy()['checkPayment']);
    }

    public function testGuardAllowsTransition(): void
    {
        $this->engine->addGuard('checkPayment', function ($entity, $context) {
            return true;
        });

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', guards: ['checkPayment']),
            ],
        );

        $entity = new Entity(['state' => 'pending']);
        $result = $this->engine->apply($definition, $entity, 'pay');

        $this->assertTrue($result->isSuccess());
    }

    public function testCommandExecutedOnTransition(): void
    {
        $executed = false;
        $this->engine->addCommand('sendNotification', function ($entity, $context) use (&$executed) {
            $executed = true;
        });

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', commands: ['sendNotification']),
            ],
        );

        $entity = new Entity(['state' => 'pending']);
        $this->engine->apply($definition, $entity, 'pay');

        $this->assertTrue($executed);
    }

    public function testCommandExceptionReturnsError(): void
    {
        $this->engine->addCommand('failingCommand', function ($entity, $context) {
            throw new RuntimeException('Command failed');
        });

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', commands: ['failingCommand']),
            ],
        );

        $entity = new Entity(['state' => 'pending']);
        $result = $this->engine->apply($definition, $entity, 'pay');

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isError());
    }

    public function testStrictModeThrowsForMissingGuard(): void
    {
        $this->engine->setStrictMode(true);

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', guards: ['nonexistentGuard']),
            ],
        );

        $entity = new Entity(['state' => 'pending']);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Guard 'nonexistentGuard' is not registered");

        $this->engine->apply($definition, $entity, 'pay');
    }

    public function testStrictModeThrowsForMissingCommand(): void
    {
        $this->engine->setStrictMode(true);

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', commands: ['nonexistentCommand']),
            ],
        );

        $entity = new Entity(['state' => 'pending']);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Command 'nonexistentCommand' is not registered");

        $this->engine->apply($definition, $entity, 'pay');
    }

    public function testRequireReasonValidation(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true, requireReasonFor: ['cancel']),
                new State('cancelled', final: true),
            ],
            transitions: [
                new Transition('cancel', ['pending'], 'cancelled'),
            ],
        );

        $entity = new Entity(['state' => 'pending']);
        $result = $this->engine->apply($definition, $entity, 'cancel');

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isBlocked());
        $this->assertArrayHasKey('reason', $result->getBlockedBy());
    }

    public function testRequireReasonPassesWithReason(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true, requireReasonFor: ['cancel']),
                new State('cancelled', final: true),
            ],
            transitions: [
                new Transition('cancel', ['pending'], 'cancelled'),
            ],
        );

        $entity = new Entity(['state' => 'pending']);
        $result = $this->engine->apply($definition, $entity, 'cancel', ['reason' => 'Customer requested cancellation']);

        $this->assertTrue($result->isSuccess());
    }

    public function testOnEnterCallbackExecuted(): void
    {
        $entered = false;
        $this->engine->addCommand('onEnterPaid', function ($entity, $context) use (&$entered) {
            $entered = true;
        });

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid', onEnter: ['onEnterPaid']),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
            ],
        );

        $entity = new Entity(['state' => 'pending']);
        $this->engine->apply($definition, $entity, 'pay');

        $this->assertTrue($entered);
    }

    public function testOnEnterFailureRevertsEntityState(): void
    {
        $this->engine->addCommand('onEnterPaid', function ($entity, $context) {
            throw new RuntimeException('onEnter failed');
        });

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid', onEnter: ['onEnterPaid']),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
            ],
        );

        $entity = new Entity(['state' => 'pending']);
        $result = $this->engine->apply($definition, $entity, 'pay');

        $this->assertTrue($result->isError());
        $this->assertSame('pending', $entity->get('state'));
    }

    public function testOnExitCallbackExecuted(): void
    {
        $exited = false;
        $this->engine->addCommand('onExitPending', function ($entity, $context) use (&$exited) {
            $exited = true;
        });

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true, onExit: ['onExitPending']),
                new State('paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
            ],
        );

        $entity = new Entity(['state' => 'pending']);
        $this->engine->apply($definition, $entity, 'pay');

        $this->assertTrue($exited);
    }

    public function testAutomaticTransitionWithCondition(): void
    {
        // Register condition that checks if payment is sufficient
        $this->engine->addCondition('isPaid', function ($entity, $context) {
            return $entity->get('payment_received') === true;
        });

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('processing'),
                new State('shipped'),
                new State('waiting_payment'),
            ],
            transitions: [
                new Transition('process', ['pending'], 'processing'),
                // Automatic transitions from processing state
                new Transition('auto_ship', ['processing'], 'shipped', automatic: true, condition: 'isPaid'),
                new Transition('auto_wait', ['processing'], 'waiting_payment', automatic: true), // Fallback
            ],
        );

        // Entity with payment received - should auto-transition to shipped
        $entity = new Entity(['state' => 'pending', 'payment_received' => true]);
        $result = $this->engine->apply($definition, $entity, 'process');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('shipped', $entity->get('state'));
    }

    public function testAutomaticTransitionFallbackBranch(): void
    {
        // Register condition that checks if payment is sufficient
        $this->engine->addCondition('isPaid', function ($entity, $context) {
            return $entity->get('payment_received') === true;
        });

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('processing'),
                new State('shipped'),
                new State('waiting_payment'),
            ],
            transitions: [
                new Transition('process', ['pending'], 'processing'),
                // Automatic transitions from processing state
                new Transition('auto_ship', ['processing'], 'shipped', automatic: true, condition: 'isPaid'),
                new Transition('auto_wait', ['processing'], 'waiting_payment', automatic: true), // Fallback
            ],
        );

        // Entity without payment - should auto-transition to waiting_payment (fallback)
        $entity = new Entity(['state' => 'pending', 'payment_received' => false]);
        $result = $this->engine->apply($definition, $entity, 'process');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('waiting_payment', $entity->get('state'));
    }

    public function testProcessAutomaticTransitionsManually(): void
    {
        $this->engine->addCondition('isApproved', function ($entity, $context) {
            return $entity->get('approved') === true;
        });

        $definition = new Definition(
            name: 'approval',
            table: 'Items',
            field: 'status',
            states: [
                new State('review', initial: true),
                new State('approved'),
                new State('rejected'),
            ],
            transitions: [
                new Transition('auto_approve', ['review'], 'approved', automatic: true, condition: 'isApproved'),
                new Transition('auto_reject', ['review'], 'rejected', automatic: true),
            ],
        );

        $entity = new Entity(['status' => 'review', 'approved' => true]);
        $result = $this->engine->processAutomaticTransitions($definition, $entity);

        $this->assertNotNull($result);
        $this->assertTrue($result->isSuccess());
        $this->assertSame('approved', $entity->get('status'));
    }

    public function testNoAutomaticTransitionsFromState(): void
    {
        $definition = new Definition(
            name: 'simple',
            table: 'Items',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('done', final: true),
            ],
            transitions: [
                new Transition('finish', ['pending'], 'done'),
            ],
        );

        $entity = new Entity(['state' => 'pending']);
        $result = $this->engine->processAutomaticTransitions($definition, $entity);

        $this->assertNull($result);
        $this->assertSame('pending', $entity->get('state')); // Unchanged
    }

    public function testChainedAutomaticTransitions(): void
    {
        $this->engine->addCondition('isUrgent', fn ($e, $c) => $e->get('urgent') === true);
        $this->engine->addCondition('isReady', fn ($e, $c) => $e->get('ready') === true);

        $definition = new Definition(
            name: 'pipeline',
            table: 'Items',
            field: 'state',
            states: [
                new State('start', initial: true),
                new State('step1'),
                new State('step2'),
                new State('done', final: true),
            ],
            transitions: [
                new Transition('begin', ['start'], 'step1'),
                // Auto from step1 to step2 if urgent
                new Transition('auto_step2', ['step1'], 'step2', automatic: true, condition: 'isUrgent'),
                // Auto from step2 to done if ready
                new Transition('auto_done', ['step2'], 'done', automatic: true, condition: 'isReady'),
            ],
        );

        $entity = new Entity(['state' => 'start', 'urgent' => true, 'ready' => true]);
        $result = $this->engine->apply($definition, $entity, 'begin');

        $this->assertTrue($result->isSuccess());
        // Should chain through: start -> step1 -> step2 -> done
        $this->assertSame('done', $entity->get('state'));
    }

    public function testStrictModeThrowsForMissingCondition(): void
    {
        $this->engine->setStrictMode(true);

        $definition = new Definition(
            name: 'test',
            table: 'Items',
            field: 'state',
            states: [
                new State('a', initial: true),
                new State('b'),
            ],
            transitions: [
                new Transition('auto_b', ['a'], 'b', automatic: true, condition: 'nonexistentCondition'),
            ],
        );

        $entity = new Entity(['state' => 'a']);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Condition 'nonexistentCondition' is not registered");

        $this->engine->processAutomaticTransitions($definition, $entity);
    }

    public function testAutomaticOnEnterFailureRevertsEntityState(): void
    {
        $this->engine->addCommand('onEnterDone', function ($entity, $context) {
            throw new RuntimeException('automatic onEnter failed');
        });

        $definition = new Definition(
            name: 'auto',
            table: 'Items',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('done', onEnter: ['onEnterDone']),
            ],
            transitions: [
                new Transition('auto_done', ['pending'], 'done', automatic: true),
            ],
        );

        $entity = new Entity(['state' => 'pending']);
        $result = $this->engine->processAutomaticTransitions($definition, $entity);

        $this->assertNotNull($result);
        $this->assertTrue($result->isError());
        $this->assertSame('pending', $entity->get('state'));
    }

    public function testAutomaticTransitionLimitPreventsInfiniteLoops(): void
    {
        $this->engine->setMaxAutomaticTransitions(2);

        $definition = new Definition(
            name: 'loop',
            table: 'Items',
            field: 'state',
            states: [
                new State('a', initial: true),
                new State('b'),
            ],
            transitions: [
                new Transition('auto_b', ['a'], 'b', automatic: true),
                new Transition('auto_a', ['b'], 'a', automatic: true),
            ],
        );

        $entity = new Entity(['state' => 'a']);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('Exceeded automatic transition limit of 2');

        $this->engine->processAutomaticTransitions($definition, $entity);
    }
}
