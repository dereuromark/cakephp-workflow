<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase;

use Cake\Event\EventManager;
use Cake\ORM\Entity;
use PHPUnit\Framework\TestCase;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Engine\StateMachineEngine;
use Workflow\Workflow;

class WorkflowTest extends TestCase
{
    private Definition $definition;

    private StateMachineEngine $engine;

    public function setUp(): void
    {
        parent::setUp();

        $this->definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('processing'),
                new State('completed', final: true, flags: ['done']),
                new State('cancelled', final: true),
            ],
            transitions: [
                new Transition('start', ['pending'], 'processing', happy: true),
                new Transition('complete', ['processing'], 'completed', happy: true),
                new Transition('cancel', ['pending', 'processing'], 'cancelled'),
            ],
        );

        $this->engine = new StateMachineEngine(new EventManager());
    }

    public function testGetStateName(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $this->assertSame('pending', $workflow->getStateName());
    }

    public function testGetStateNameReturnsInitialForNewEntity(): void
    {
        $entity = new Entity();
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $this->assertSame('pending', $workflow->getStateName());
    }

    public function testGetState(): void
    {
        $entity = new Entity(['state' => 'completed']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $state = $workflow->getState();
        $this->assertSame('completed', $state->getName());
        $this->assertTrue($state->isFinal());
    }

    public function testIsInState(): void
    {
        $entity = new Entity(['state' => 'processing']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $this->assertTrue($workflow->isInState('processing'));
        $this->assertFalse($workflow->isInState('pending'));
    }

    public function testIsInFinalState(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);
        $this->assertFalse($workflow->isInFinalState());

        $entity->set('state', 'completed');
        $this->assertTrue($workflow->isInFinalState());
    }

    public function testHasFlag(): void
    {
        $entity = new Entity(['state' => 'completed']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $this->assertTrue($workflow->hasFlag('done'));
        $this->assertFalse($workflow->hasFlag('nonexistent'));
    }

    public function testCan(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $this->assertTrue($workflow->can('start'));
        $this->assertTrue($workflow->can('cancel'));
        $this->assertFalse($workflow->can('complete'));
    }

    public function testCanReturnsFalseForFinalState(): void
    {
        $entity = new Entity(['state' => 'completed']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $this->assertFalse($workflow->can('start'));
        $this->assertFalse($workflow->can('cancel'));
    }

    public function testApply(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $result = $workflow->apply('start');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('pending', $result->getFromState());
        $this->assertSame('processing', $result->getToState());
        $this->assertSame('processing', $entity->get('state'));
    }

    public function testApplyBlockedTransition(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $result = $workflow->apply('complete');

        $this->assertTrue($result->isBlocked());
        $this->assertSame('pending', $entity->get('state'));
    }

    public function testGetAvailableTransitions(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $transitions = $workflow->getAvailableTransitions();

        $this->assertCount(2, $transitions);
        $this->assertContains('start', $transitions);
        $this->assertContains('cancel', $transitions);
    }

    public function testGetEnabledTransitions(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $transitions = $workflow->getEnabledTransitions();

        $this->assertCount(2, $transitions);
        $this->assertContains('start', $transitions);
        $this->assertContains('cancel', $transitions);
    }

    public function testGetEnabledTransitionsWithGuard(): void
    {
        $this->engine->addGuard('blockCancel', fn () => false);

        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('processing'),
                new State('cancelled', final: true),
            ],
            transitions: [
                new Transition('start', ['pending'], 'processing'),
                new Transition('cancel', ['pending'], 'cancelled', guards: ['blockCancel']),
            ],
        );

        $entity = new Entity(['state' => 'pending']);
        $workflow = new Workflow($definition, $entity, $this->engine);

        $enabled = $workflow->getEnabledTransitions();
        $available = $workflow->getAvailableTransitions();

        $this->assertCount(1, $enabled);
        $this->assertContains('start', $enabled);
        $this->assertNotContains('cancel', $enabled);

        $this->assertCount(2, $available);
        $this->assertContains('cancel', $available);
    }

    public function testGetDefinition(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $this->assertSame($this->definition, $workflow->getDefinition());
    }

    public function testGetEntity(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $this->assertSame($entity, $workflow->getEntity());
    }

    public function testGetName(): void
    {
        $entity = new Entity(['state' => 'pending']);
        $workflow = new Workflow($this->definition, $entity, $this->engine);

        $this->assertSame('order', $workflow->getName());
    }
}
