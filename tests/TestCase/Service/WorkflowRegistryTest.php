<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Service;

use Cake\Event\EventManager;
use Cake\ORM\Entity;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Engine\EngineInterface;
use Workflow\Engine\StateMachineEngine;
use Workflow\Exception\WorkflowException;
use Workflow\Loader\LoaderInterface;
use Workflow\Service\WorkflowRegistry;

#[AllowMockObjectsWithoutExpectations]
class WorkflowRegistryTest extends TestCase
{
    private WorkflowRegistry $registry;

    private LoaderInterface $mockLoader;

    private EventManager $eventManager;

    private Definition $testDefinition;

    public function setUp(): void
    {
        $this->testDefinition = $this->createTestDefinition();
        $this->mockLoader = $this->createMock(LoaderInterface::class);
        $this->eventManager = new EventManager();
        $this->registry = new WorkflowRegistry($this->mockLoader, $this->eventManager);
    }

    private function createTestDefinition(): Definition
    {
        return new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('completed', final: true),
            ],
            transitions: [
                new Transition('complete', ['pending'], 'completed'),
            ],
        );
    }

    public function testHasWorkflowReturnsTrueWhenSupported(): void
    {
        $this->mockLoader->method('supports')->willReturnCallback(function ($name) {
            return $name === 'order';
        });

        $this->assertTrue($this->registry->hasWorkflow('order'));
        $this->assertFalse($this->registry->hasWorkflow('other'));
    }

    public function testGetWorkflowReturnsDefinition(): void
    {
        $this->mockLoader->method('supports')->willReturn(true);
        $this->mockLoader->method('load')->willReturn($this->testDefinition);

        $definition = $this->registry->getWorkflow('order');

        $this->assertSame($this->testDefinition, $definition);
    }

    public function testGetWorkflowThrowsForUnsupportedWorkflow(): void
    {
        $this->mockLoader->method('supports')->willReturn(false);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Workflow 'nonexistent' not found");

        $this->registry->getWorkflow('nonexistent');
    }

    public function testGetWorkflowNamesReturnsLoaderNames(): void
    {
        $this->mockLoader->method('getWorkflowNames')->willReturn(['order', 'payment', 'shipping']);

        $names = $this->registry->getWorkflowNames();

        $this->assertSame(['order', 'payment', 'shipping'], $names);
    }

    public function testGetEngineCreatesStateMachineEngine(): void
    {
        $engine = $this->registry->getEngine('order');

        $this->assertInstanceOf(StateMachineEngine::class, $engine);
    }

    public function testGetEngineCachesEngine(): void
    {
        $engine1 = $this->registry->getEngine('order');
        $engine2 = $this->registry->getEngine('order');

        $this->assertSame($engine1, $engine2);
    }

    public function testGetEngineReturnsDifferentEnginesPerWorkflow(): void
    {
        $engine1 = $this->registry->getEngine('order');
        $engine2 = $this->registry->getEngine('payment');

        $this->assertNotSame($engine1, $engine2);
    }

    public function testSetEngineOverridesDefault(): void
    {
        $customEngine = $this->createMock(EngineInterface::class);

        $this->registry->setEngine('order', $customEngine);

        $engine = $this->registry->getEngine('order');
        $this->assertSame($customEngine, $engine);
    }

    public function testSetEngineDoesNotAffectOtherWorkflows(): void
    {
        $customEngine = $this->createMock(EngineInterface::class);

        $this->registry->setEngine('order', $customEngine);

        $orderEngine = $this->registry->getEngine('order');
        $paymentEngine = $this->registry->getEngine('payment');

        $this->assertSame($customEngine, $orderEngine);
        $this->assertNotSame($customEngine, $paymentEngine);
        $this->assertInstanceOf(StateMachineEngine::class, $paymentEngine);
    }

    public function testGetEngineHonorsConfiguredStrictMode(): void
    {
        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('supports')->willReturn(true);
        $loader->method('load')->willReturn(new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', guards: ['missingGuard']),
            ],
        ));

        $registry = new WorkflowRegistry($loader, new EventManager(), true, 10);
        $engine = $registry->getEngine('order');

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Guard 'missingGuard' is not registered");

        $engine->apply($registry->getWorkflow('order'), new Entity(['state' => 'pending']), 'pay');
    }

    public function testGetWorkflowWithLoader(): void
    {
        $definition2 = new Definition(
            name: 'payment',
            table: 'Payments',
            field: 'status',
            states: [new State('pending', initial: true)],
            transitions: [],
        );

        $this->mockLoader->method('supports')->willReturn(true);
        $this->mockLoader->method('load')->willReturnCallback(function ($name) use ($definition2) {
            return $name === 'order' ? $this->testDefinition : $definition2;
        });

        $order = $this->registry->getWorkflow('order');
        $payment = $this->registry->getWorkflow('payment');

        $this->assertSame('order', $order->getName());
        $this->assertSame('payment', $payment->getName());
    }
}
