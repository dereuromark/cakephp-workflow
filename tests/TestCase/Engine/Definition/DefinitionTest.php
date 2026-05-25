<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Engine\Definition;

use PHPUnit\Framework\TestCase;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Exception\WorkflowException;

class DefinitionTest extends TestCase
{
    private Definition $definition;

    protected function setUp(): void
    {
        $this->definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
                new State('completed', final: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
                new Transition('complete', ['paid'], 'completed'),
            ],
        );
    }

    public function testGetName(): void
    {
        $this->assertSame('order', $this->definition->getName());
    }

    public function testGetTable(): void
    {
        $this->assertSame('Orders', $this->definition->getTable());
    }

    public function testGetField(): void
    {
        $this->assertSame('state', $this->definition->getField());
    }

    public function testGetStates(): void
    {
        $states = $this->definition->getStates();
        $this->assertCount(3, $states);
    }

    public function testGetTransitions(): void
    {
        $transitions = $this->definition->getTransitions();
        $this->assertCount(2, $transitions);
    }

    public function testHasState(): void
    {
        $this->assertTrue($this->definition->hasState('pending'));
        $this->assertTrue($this->definition->hasState('paid'));
        $this->assertFalse($this->definition->hasState('nonexistent'));
    }

    public function testGetState(): void
    {
        $state = $this->definition->getState('pending');
        $this->assertSame('pending', $state->getName());
        $this->assertTrue($state->isInitial());
    }

    public function testGetStateThrowsForNonexistent(): void
    {
        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("State 'nonexistent' not found");

        $this->definition->getState('nonexistent');
    }

    public function testFindStateReturnsNullForMissing(): void
    {
        $this->assertNull($this->definition->findState('nonexistent'));
    }

    public function testFindStateReturnsState(): void
    {
        $state = $this->definition->findState('paid');
        $this->assertNotNull($state);
        $this->assertSame('paid', $state->getName());
    }

    public function testResolveStateReturnsRealState(): void
    {
        $state = $this->definition->resolveState('paid');
        $this->assertSame('paid', $state->getName());
        $this->assertFalse($state->isUnknown());
    }

    public function testResolveStateReturnsUnknownForMissing(): void
    {
        $state = $this->definition->resolveState('ghost');
        $this->assertSame('ghost', $state->getName());
        $this->assertTrue($state->isUnknown());
    }

    public function testGetInitialState(): void
    {
        $state = $this->definition->getInitialState();
        $this->assertSame('pending', $state->getName());
        $this->assertTrue($state->isInitial());
    }

    public function testGetInitialStateThrowsWhenMissing(): void
    {
        $definition = new Definition(
            name: 'broken',
            table: 'Broken',
            field: 'state',
            states: [
                new State('first'),
                new State('second'),
            ],
            transitions: [],
        );

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('No initial state defined');

        $definition->getInitialState();
    }

    public function testGetFinalStates(): void
    {
        $finalStates = $this->definition->getFinalStates();
        $this->assertCount(1, $finalStates);
        $this->assertSame('completed', $finalStates[2]->getName());
    }

    public function testGetTransitionsFromState(): void
    {
        $transitions = $this->definition->getTransitionsFromState('pending');
        $this->assertCount(1, $transitions);
        $this->assertSame('pay', $transitions[0]->getName());
    }

    public function testGetTransition(): void
    {
        $transition = $this->definition->getTransition('pay');
        $this->assertSame('pay', $transition->getName());
        $this->assertSame('paid', $transition->getTo());
    }

    public function testGetTransitionThrowsForNonexistent(): void
    {
        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Transition 'nonexistent' not found");

        $this->definition->getTransition('nonexistent');
    }

    public function testGetVersionHash(): void
    {
        $hash = $this->definition->getVersionHash();
        $this->assertSame(8, strlen($hash));

        // Same definition should produce same hash
        $definition2 = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
                new State('completed', final: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
                new Transition('complete', ['paid'], 'completed'),
            ],
        );

        $this->assertSame($hash, $definition2->getVersionHash());
    }

    public function testGetStatesWithFlag(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true, flags: ['editable']),
                new State('paid', flags: ['editable', 'refundable']),
                new State('completed', final: true),
            ],
            transitions: [],
        );

        $editableStates = $definition->getStatesWithFlag('editable');
        $this->assertCount(2, $editableStates);

        $refundableStates = $definition->getStatesWithFlag('refundable');
        $this->assertCount(1, $refundableStates);

        $noneStates = $definition->getStatesWithFlag('nonexistent');
        $this->assertEmpty($noneStates);
    }

    public function testStuckAutomaticBranchDetection(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('stuck'),
                new State('with_fallback'),
                new State('with_manual_exit'),
                new State('single_auto'),
                new State('done', final: true),
                new State('other', final: true),
            ],
            transitions: [
                new Transition('start', ['pending'], 'stuck'),
                // stuck: two conditional automatic transitions, no fallback, no other exit.
                new Transition('a1', ['stuck'], 'done', automatic: true, condition: 'condA'),
                new Transition('a2', ['stuck'], 'other', automatic: true, condition: 'condB'),
                // with_fallback: a branch but one transition is an unconditional fallback.
                new Transition('b1', ['with_fallback'], 'done', automatic: true, condition: 'condA'),
                new Transition('b2', ['with_fallback'], 'other', automatic: true),
                // with_manual_exit: a conditional-only branch but also a manual exit.
                new Transition('c1', ['with_manual_exit'], 'done', automatic: true, condition: 'condA'),
                new Transition('c2', ['with_manual_exit'], 'other', automatic: true, condition: 'condB'),
                new Transition('c_manual', ['with_manual_exit'], 'done'),
                // single_auto: a lone conditional automatic transition (advance-when-ready).
                new Transition('d1', ['single_auto'], 'done', automatic: true, condition: 'condA'),
            ],
        );

        $this->assertTrue($definition->isStuckAutomaticBranchState('stuck'));
        $this->assertFalse($definition->isStuckAutomaticBranchState('with_fallback'));
        $this->assertFalse($definition->isStuckAutomaticBranchState('with_manual_exit'));
        $this->assertFalse($definition->isStuckAutomaticBranchState('single_auto'));
        $this->assertFalse($definition->isStuckAutomaticBranchState('done'));
        $this->assertFalse($definition->isStuckAutomaticBranchState('missing'));

        $this->assertSame(['stuck'], $definition->getStuckAutomaticBranchStates());
    }
}
