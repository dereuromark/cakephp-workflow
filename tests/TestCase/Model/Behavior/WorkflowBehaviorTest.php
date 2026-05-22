<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Model\Behavior;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventManager;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\StateTimeout;
use Workflow\Engine\Definition\Transition;
use Workflow\Engine\TransitionResult;
use Workflow\Exception\WorkflowException;
use Workflow\Loader\LoaderInterface;
use Workflow\Model\Behavior\WorkflowBehavior;
use Workflow\Model\Entity\WorkflowLock;
use Workflow\Service\LockManager;
use Workflow\Service\TimeoutScheduler;
use Workflow\Service\TransitionLogger;
use Workflow\Service\WorkflowRegistry;

#[AllowMockObjectsWithoutExpectations]
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
                new State('paid', timeouts: [new StateTimeout('PT1H', 'ship')]),
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
        $loader = $this->createMock(LoaderInterface::class);
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
            'useTransaction' => false, // No DB connection in mock tests
            'useLocking' => false, // No DB connection in mock tests
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

    public function testIsFinalReturnsFalseForOrphanedStateWithoutThrowing(): void
    {
        $behavior = $this->addBehavior();
        $entity = new Entity(['state' => 'ghost']);

        $this->assertFalse($behavior->isFinal($entity));
    }

    public function testHasFlagReturnsFalseForOrphanedStateWithoutThrowing(): void
    {
        $behavior = $this->addBehavior();
        $entity = new Entity(['state' => 'ghost']);

        $this->assertFalse($behavior->hasFlag($entity, 'done'));
    }

    public function testVersionStampWrittenOnTransitionWhenVersioningEnabled(): void
    {
        $behavior = $this->addBehavior(['versioning' => true]);
        $entity = new Entity(['state' => 'pending']);

        $behavior->applyTransition($entity, 'pay');

        $this->assertSame(
            $this->definition->getVersionHash(),
            $entity->get('workflow_version'),
        );
    }

    public function testNoVersionStampWhenVersioningDisabled(): void
    {
        $behavior = $this->addBehavior();
        $entity = new Entity(['state' => 'pending']);

        $behavior->applyTransition($entity, 'pay');

        $this->assertNull($entity->get('workflow_version'));
    }

    public function testNoVersionStampWhenColumnAbsent(): void
    {
        $table = new Table(['table' => 'orders', 'alias' => 'Orders']);
        $table->setSchema([
            'id' => ['type' => 'integer'],
            'state' => ['type' => 'string'],
        ]);
        $table->addBehavior('Workflow', [
            'className' => WorkflowBehavior::class,
            'workflow' => 'order',
            'registry' => $this->registry,
            'versioning' => true,
            'useTransaction' => false,
            'useLocking' => false,
        ]);
        /** @var \Workflow\Model\Behavior\WorkflowBehavior $behavior */
        $behavior = $table->behaviors()->get('Workflow');
        $entity = new Entity(['state' => 'pending']);

        $behavior->applyTransition($entity, 'pay');

        $this->assertNull($entity->get('workflow_version'));
        $table->removeBehavior('Workflow');
    }

    public function testNewEntityStampedOnBeforeSaveWhenVersioningEnabled(): void
    {
        $this->addBehavior(['versioning' => true]);
        $entity = new Entity(['state' => 'pending']);
        $entity->setNew(true);

        $this->table->dispatchEvent('Model.beforeSave', [
            'entity' => $entity,
            'options' => new ArrayObject(),
        ]);

        $this->assertSame(
            $this->definition->getVersionHash(),
            $entity->get('workflow_version'),
        );
    }

    public function testNewEntityStampOverwritesClientSuppliedValue(): void
    {
        $this->addBehavior(['versioning' => true]);
        $entity = new Entity(['state' => 'pending', 'workflow_version' => 'stale123']);
        $entity->setNew(true);

        $this->table->dispatchEvent('Model.beforeSave', [
            'entity' => $entity,
            'options' => new ArrayObject(),
        ]);

        $this->assertSame(
            $this->definition->getVersionHash(),
            $entity->get('workflow_version'),
        );
    }

    public function testGetVersionStampReturnsStamp(): void
    {
        $behavior = $this->addBehavior(['versioning' => true]);
        $entity = new Entity(['state' => 'paid', 'workflow_version' => 'abc12345']);

        $this->assertSame('abc12345', $behavior->getVersionStamp($entity));
    }

    public function testIsStaleTrueForOutdatedStamp(): void
    {
        $behavior = $this->addBehavior(['versioning' => true]);
        $entity = new Entity(['state' => 'paid', 'workflow_version' => 'outdated']);

        $this->assertTrue($behavior->isStale($entity));
    }

    public function testIsStaleFalseForCurrentStamp(): void
    {
        $behavior = $this->addBehavior(['versioning' => true]);
        $entity = new Entity([
            'state' => 'paid',
            'workflow_version' => $this->definition->getVersionHash(),
        ]);

        $this->assertFalse($behavior->isStale($entity));
    }

    public function testIsStaleFalseForNullStamp(): void
    {
        $behavior = $this->addBehavior(['versioning' => true]);
        $entity = new Entity(['state' => 'paid', 'workflow_version' => null]);

        $this->assertFalse($behavior->isStale($entity));
    }

    public function testIsStaleFalseWhenVersioningDisabled(): void
    {
        $behavior = $this->addBehavior();
        $entity = new Entity(['state' => 'paid', 'workflow_version' => 'outdated']);

        $this->assertFalse($behavior->isStale($entity));
    }

    public function testTransitionTemporarilyEnablesPersistenceOptions(): void
    {
        $table = new class (['table' => 'orders', 'alias' => 'Orders']) extends Table {
            public bool $saved = false;

            public function saveOrFail(
                EntityInterface $entity,
                array $options = [],
            ): EntityInterface {
                $this->saved = true;

                return $entity;
            }
        };

        $this->table = $table;
        $behavior = $this->addBehavior([
            'autoSave' => false,
            'autoLog' => false,
            'useTransaction' => false,
            'useLocking' => false,
        ]);

        $logger = new class () extends TransitionLogger {
            public int $calls = 0;

            public function log(
                string $workflowName,
                string $entityTable,
                EntityInterface $entity,
                TransitionResult $result,
                string $transitionName,
                array $context = [],
                ?string $workflowVersion = null,
            ): void {
                $this->calls++;
            }
        };
        $behavior->setLogger($logger);

        $entity = new Entity(['id' => 1, 'state' => 'pending']);
        $result = $behavior->transition($entity, 'pay', [], [
            'save' => true,
            'log' => true,
            'lock' => false,
            'transaction' => false,
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($table->saved);
        $this->assertSame(1, $logger->calls);
        $this->assertFalse($behavior->getConfig('autoSave'));
        $this->assertFalse($behavior->getConfig('autoLog'));
    }

    public function testTransitionUsesInjectedLockManagerWhenRequested(): void
    {
        $behavior = $this->addBehavior([
            'autoSave' => false,
            'autoLog' => false,
            'useTransaction' => false,
            'useLocking' => false,
        ]);

        $lockManager = new class () extends LockManager {
            public int $acquireCalls = 0;

            public int $releaseCalls = 0;

            public function acquire(
                string $workflowName,
                string $entityTable,
                EntityInterface $entity,
                ?string $lockedBy = null,
            ): ?WorkflowLock {
                $this->acquireCalls++;

                return new WorkflowLock([
                    'workflow_name' => $workflowName,
                    'entity_table' => $entityTable,
                    'entity_id' => (string)$entity->get('id'),
                ]);
            }

            public function release(
                string $workflowName,
                string $entityTable,
                EntityInterface $entity,
            ): void {
                $this->releaseCalls++;
            }
        };
        $behavior->setLockManager($lockManager);

        $entity = new Entity(['id' => 1, 'state' => 'pending']);
        $result = $behavior->transition($entity, 'pay', [], [
            'save' => false,
            'log' => false,
            'lock' => true,
            'transaction' => false,
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1, $lockManager->acquireCalls);
        $this->assertSame(1, $lockManager->releaseCalls);
    }

    public function testTransitionUsesInjectedTimeoutSchedulerWhenPersisting(): void
    {
        $table = new class (['table' => 'orders', 'alias' => 'Orders']) extends Table {
            public function saveOrFail(
                EntityInterface $entity,
                array $options = [],
            ): EntityInterface {
                return $entity;
            }
        };

        $this->table = $table;
        $behavior = $this->addBehavior([
            'autoSave' => false,
            'autoLog' => false,
            'useTransaction' => false,
            'useLocking' => false,
            'useTimeouts' => false,
        ]);

        $scheduler = new class () extends TimeoutScheduler {
            public int $calls = 0;

            public ?string $stateName = null;

            public function syncStateTimeouts(
                string $workflowName,
                string $entityTable,
                EntityInterface $entity,
                State $state,
            ): void {
                $this->calls++;
                $this->stateName = $state->getName();
            }
        };
        $behavior->setTimeoutScheduler($scheduler);

        $entity = new Entity(['id' => 1, 'state' => 'pending']);
        $result = $behavior->transition($entity, 'pay', [], [
            'save' => true,
            'log' => false,
            'lock' => false,
            'timeouts' => true,
            'transaction' => false,
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1, $scheduler->calls);
        $this->assertSame('paid', $scheduler->stateName);
    }

    public function testTransitionDoesNotScheduleTimeoutsWithoutPersistence(): void
    {
        $behavior = $this->addBehavior([
            'autoSave' => false,
            'autoLog' => false,
            'useTransaction' => false,
            'useLocking' => false,
            'useTimeouts' => false,
        ]);

        $scheduler = new class () extends TimeoutScheduler {
            public int $calls = 0;

            public function syncStateTimeouts(
                string $workflowName,
                string $entityTable,
                EntityInterface $entity,
                State $state,
            ): void {
                $this->calls++;
            }
        };
        $behavior->setTimeoutScheduler($scheduler);

        $entity = new Entity(['id' => 1, 'state' => 'pending']);
        $result = $behavior->transition($entity, 'pay', [], [
            'save' => false,
            'log' => false,
            'lock' => false,
            'timeouts' => true,
            'transaction' => false,
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(0, $scheduler->calls);
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
            'options' => new ArrayObject(),
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
            'options' => new ArrayObject(),
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
            'options' => new ArrayObject(),
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
            'options' => new ArrayObject(),
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
            'options' => new ArrayObject(),
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

    public function testGetStateNamesWithFlag(): void
    {
        $behavior = $this->addBehavior();

        // 'done' flag is on 'completed' state
        $stateNames = $behavior->getStateNamesWithFlag('done');
        $this->assertSame(['completed'], $stateNames);

        // Non-existent flag
        $stateNames = $behavior->getStateNamesWithFlag('nonexistent');
        $this->assertSame([], $stateNames);
    }

    public function testGetStateNamesWithoutFlag(): void
    {
        $behavior = $this->addBehavior();

        // States without 'done' flag (all except 'completed')
        $stateNames = $behavior->getStateNamesWithoutFlag('done');
        $this->assertContains('pending', $stateNames);
        $this->assertContains('paid', $stateNames);
        $this->assertContains('shipped', $stateNames);
        $this->assertContains('cancelled', $stateNames);
        $this->assertNotContains('completed', $stateNames);
    }

    public function testGetFinalStateNames(): void
    {
        $behavior = $this->addBehavior();

        $finalStates = $behavior->getFinalStateNames();

        $this->assertContains('completed', $finalStates);
        $this->assertContains('cancelled', $finalStates);
        $this->assertNotContains('pending', $finalStates);
        $this->assertNotContains('paid', $finalStates);
        $this->assertNotContains('shipped', $finalStates);
    }
}
