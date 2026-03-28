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
        parent::setUp();
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
}
