<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Model\Behavior;

use Cake\Event\EventManager;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Exception\WorkflowException;
use Workflow\Model\Behavior\WorkflowBehavior;
use Workflow\Service\WorkflowRegistry;

class WorkflowBehaviorTest extends TestCase
{
    private Table $table;

    private WorkflowRegistry $registry;

    private Definition $definition;

    public function setUp(): void
    {
        parent::setUp();

        $this->definition = $this->createTestDefinition();
        $this->registry = $this->createMockRegistry();

        $this->table = new Table([
            'table' => 'orders',
            'alias' => 'Orders',
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        if ($this->table->behaviors()->has('Workflow')) {
            $this->table->removeBehavior('Workflow');
        }
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
                new State('completed', final: true, flags: ['done']),
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

    private function createMockRegistry(): WorkflowRegistry
    {
        $loader = $this->createMock(\Workflow\Loader\LoaderInterface::class);
        $loader->method('supports')->willReturn(true);
        $loader->method('load')->willReturn($this->definition);

        return new WorkflowRegistry($loader, new EventManager());
    }

    private function addBehavior(array $config = []): WorkflowBehavior
    {
        $defaultConfig = [
            'className' => WorkflowBehavior::class,
            'workflow' => 'order',
            'registry' => $this->registry,
        ];

        $this->table->addBehavior('Workflow', array_merge($defaultConfig, $config));

        return $this->table->behaviors()->get('Workflow');
    }

    public function testInitializeRequiresWorkflowName(): void
    {
        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('WorkflowBehavior requires a workflow name');

        $this->table->addBehavior('Workflow', [
            'className' => WorkflowBehavior::class,
            'registry' => $this->registry,
        ]);
    }

    public function testGetWorkflowDefinition(): void
    {
        $behavior = $this->addBehavior();
        $definition = $behavior->getWorkflowDefinition();

        $this->assertSame('order', $definition->getName());
        $this->assertSame('Orders', $definition->getTable());
    }

    public function testCanTransition(): void
    {
        $behavior = $this->addBehavior();
        $entity = new Entity(['state' => 'pending']);

        $this->assertTrue($behavior->canTransition($entity, 'pay'));
        $this->assertTrue($behavior->canTransition($entity, 'cancel'));
        $this->assertFalse($behavior->canTransition($entity, 'ship'));
        $this->assertFalse($behavior->canTransition($entity, 'complete'));
    }

    public function testApplyTransition(): void
    {
        $behavior = $this->addBehavior();
        $entity = new Entity(['state' => 'pending']);

        $result = $behavior->applyTransition($entity, 'pay');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('paid', $entity->get('state'));
    }

    public function testApplyTransitionFails(): void
    {
        $behavior = $this->addBehavior();
        $entity = new Entity(['state' => 'pending']);

        $result = $behavior->applyTransition($entity, 'ship');

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isBlocked());
        $this->assertSame('pending', $entity->get('state'));
    }

    public function testGetAvailableTransitions(): void
    {
        $behavior = $this->addBehavior();
        $entity = new Entity(['state' => 'pending']);

        $transitions = $behavior->getAvailableTransitions($entity);

        $this->assertContains('pay', $transitions);
        $this->assertContains('cancel', $transitions);
        $this->assertNotContains('ship', $transitions);
    }

    public function testGetCurrentState(): void
    {
        $behavior = $this->addBehavior();

        $entity = new Entity(['state' => 'paid']);
        $this->assertSame('paid', $behavior->getCurrentState($entity));

        $entityWithNull = new Entity(['state' => null]);
        $this->assertSame('pending', $behavior->getCurrentState($entityWithNull));
    }

    public function testIsInState(): void
    {
        $behavior = $this->addBehavior();
        $entity = new Entity(['state' => 'paid']);

        $this->assertTrue($behavior->isInState($entity, 'paid'));
        $this->assertFalse($behavior->isInState($entity, 'pending'));
    }

    public function testIsFinal(): void
    {
        $behavior = $this->addBehavior();

        $pendingEntity = new Entity(['state' => 'pending']);
        $this->assertFalse($behavior->isFinal($pendingEntity));

        $completedEntity = new Entity(['state' => 'completed']);
        $this->assertTrue($behavior->isFinal($completedEntity));
    }

    public function testHasFlag(): void
    {
        $behavior = $this->addBehavior();

        $completedEntity = new Entity(['state' => 'completed']);
        $this->assertTrue($behavior->hasFlag($completedEntity, 'done'));
        $this->assertFalse($behavior->hasFlag($completedEntity, 'processing'));

        $pendingEntity = new Entity(['state' => 'pending']);
        $this->assertFalse($behavior->hasFlag($pendingEntity, 'done'));
    }

    public function testBeforeSaveBlocksDirectStateChange(): void
    {
        $behavior = $this->addBehavior(['validateOnSave' => true]);

        $entity = new Entity(['id' => 1, 'state' => 'pending']);
        $entity->setNew(false);
        $entity->setSource('Orders');
        $entity->clean();

        // Try to change state directly
        $entity->set('state', 'paid');

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('Direct state changes are not allowed');

        $this->table->dispatchEvent('Model.beforeSave', [
            'entity' => $entity,
            'options' => new \ArrayObject(),
        ]);
    }

    public function testBeforeSaveAllowsInitialStateForNewEntity(): void
    {
        $behavior = $this->addBehavior(['validateOnSave' => true]);

        $entity = new Entity(['state' => 'pending']);
        $entity->setNew(true);

        // Should not throw
        $this->table->dispatchEvent('Model.beforeSave', [
            'entity' => $entity,
            'options' => new \ArrayObject(),
        ]);

        $this->assertTrue(true);
    }

    public function testBeforeSaveBlocksInvalidInitialStateForNewEntity(): void
    {
        $behavior = $this->addBehavior(['validateOnSave' => true]);

        $entity = new Entity(['state' => 'paid']);
        $entity->setNew(true);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Cannot set initial state to 'paid'");

        $this->table->dispatchEvent('Model.beforeSave', [
            'entity' => $entity,
            'options' => new \ArrayObject(),
        ]);
    }

    public function testBeforeSaveAllowsWorkflowTransition(): void
    {
        $behavior = $this->addBehavior(['validateOnSave' => true]);

        $entity = new Entity(['id' => 1, 'state' => 'pending']);
        $entity->setNew(false);
        $entity->clean();

        // Apply transition through workflow
        $behavior->applyTransition($entity, 'pay');

        // Should not throw during beforeSave
        $this->table->dispatchEvent('Model.beforeSave', [
            'entity' => $entity,
            'options' => new \ArrayObject(),
        ]);

        $this->assertSame('paid', $entity->get('state'));
    }

    public function testBeforeSaveValidationCanBeDisabled(): void
    {
        $behavior = $this->addBehavior(['validateOnSave' => false]);

        $entity = new Entity(['id' => 1, 'state' => 'pending']);
        $entity->setNew(false);
        $entity->clean();

        // Direct state change
        $entity->set('state', 'paid');

        // Should not throw when validation is disabled
        $this->table->dispatchEvent('Model.beforeSave', [
            'entity' => $entity,
            'options' => new \ArrayObject(),
        ]);

        $this->assertTrue(true);
    }

    public function testGetRegistryThrowsWhenNotConfigured(): void
    {
        $this->table->addBehavior('Workflow', [
            'className' => WorkflowBehavior::class,
            'workflow' => 'order',
        ]);

        $behavior = $this->table->behaviors()->get('Workflow');

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('WorkflowBehavior requires a registry instance');

        $behavior->getWorkflowDefinition();
    }
}
