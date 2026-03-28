<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Engine\Definition;

use PHPUnit\Framework\TestCase;
use Workflow\Engine\Definition\State;

class StateTest extends TestCase
{
    public function testBasicState(): void
    {
        $state = new State('pending');

        $this->assertSame('pending', $state->getName());
        $this->assertNull($state->getLabel());
        $this->assertSame('pending', $state->getDisplayName());
        $this->assertNull($state->getColor());
        $this->assertFalse($state->isInitial());
        $this->assertFalse($state->isFinal());
        $this->assertFalse($state->isFailed());
        $this->assertEmpty($state->getFlags());
    }

    public function testInitialState(): void
    {
        $state = new State('pending', initial: true);

        $this->assertTrue($state->isInitial());
        $this->assertFalse($state->isFinal());
    }

    public function testFinalState(): void
    {
        $state = new State('completed', final: true);

        $this->assertFalse($state->isInitial());
        $this->assertTrue($state->isFinal());
        $this->assertFalse($state->isFailed());
    }

    public function testFailedState(): void
    {
        $state = new State('error', failed: true);

        $this->assertTrue($state->isFinal());
        $this->assertTrue($state->isFailed());
    }

    public function testStateWithLabel(): void
    {
        $state = new State('pending', label: 'Pending Review');

        $this->assertSame('Pending Review', $state->getLabel());
        $this->assertSame('Pending Review', $state->getDisplayName());
    }

    public function testStateWithColor(): void
    {
        $state = new State('pending', color: '#FFA500');

        $this->assertSame('#FFA500', $state->getColor());
    }

    public function testStateWithFlags(): void
    {
        $state = new State('pending', flags: ['editable', 'cancelable']);

        $this->assertSame(['editable', 'cancelable'], $state->getFlags());
        $this->assertTrue($state->hasFlag('editable'));
        $this->assertTrue($state->hasFlag('cancelable'));
        $this->assertFalse($state->hasFlag('nonexistent'));
    }

    public function testStateWithOnEnterCallbacks(): void
    {
        $state = new State('paid', onEnter: ['sendReceipt', 'notifyAdmin']);

        $this->assertSame(['sendReceipt', 'notifyAdmin'], $state->getOnEnter());
    }

    public function testStateWithOnExitCallbacks(): void
    {
        $state = new State('pending', onExit: ['logExit', 'cleanup']);

        $this->assertSame(['logExit', 'cleanup'], $state->getOnExit());
    }

    public function testStateWithRequireReasonFor(): void
    {
        $state = new State('active', requireReasonFor: ['cancel', 'suspend']);

        $this->assertSame(['cancel', 'suspend'], $state->getRequireReasonFor());
        $this->assertTrue($state->requiresReasonFor('cancel'));
        $this->assertTrue($state->requiresReasonFor('suspend'));
        $this->assertFalse($state->requiresReasonFor('complete'));
    }

    public function testFullyConfiguredState(): void
    {
        $state = new State(
            name: 'active',
            label: 'Active Order',
            color: '#00FF00',
            initial: false,
            final: false,
            failed: false,
            flags: ['editable'],
            onEnter: ['sendWelcome'],
            onExit: ['logExit'],
            requireReasonFor: ['cancel'],
        );

        $this->assertSame('active', $state->getName());
        $this->assertSame('Active Order', $state->getLabel());
        $this->assertSame('#00FF00', $state->getColor());
        $this->assertFalse($state->isInitial());
        $this->assertFalse($state->isFinal());
        $this->assertTrue($state->hasFlag('editable'));
        $this->assertSame(['sendWelcome'], $state->getOnEnter());
        $this->assertSame(['logExit'], $state->getOnExit());
        $this->assertTrue($state->requiresReasonFor('cancel'));
    }
}
