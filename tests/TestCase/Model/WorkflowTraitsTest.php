<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Model;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Cake\ORM\Locator\LocatorAwareTrait;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use TestApp\Model\Entity\TraitOrder;
use TestApp\Model\Table\TraitOrdersTable;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Exception\WorkflowException;
use Workflow\Loader\LoaderInterface;
use Workflow\Service\WorkflowRegistry;
use Workflow\Test\TestCase\DatabaseTestCase;

/**
 * Covers the developer-facing convenience traits: WorkflowTableTrait (table-level)
 * and WorkflowTrait (entity-level).
 */
#[AllowMockObjectsWithoutExpectations]
class WorkflowTraitsTest extends DatabaseTestCase
{
    use LocatorAwareTrait;

    private TraitOrdersTable $orders;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();

        Configure::write('Workflow.registry', $this->createRegistry());

        $this->getTableLocator()->clear();
        /** @var \TestApp\Model\Table\TraitOrdersTable $orders */
        $orders = $this->getTableLocator()->get('Orders', [
            'className' => TraitOrdersTable::class,
            'connection' => ConnectionManager::get('test'),
        ]);
        $this->orders = $orders;
    }

    public function tearDown(): void
    {
        Configure::delete('Workflow.registry');
        $this->getTableLocator()->clear();
    }

    public function testTableTraitExposesTypedWorkflowApi(): void
    {
        $order = $this->orders->newEntity(['state' => 'pending']);
        $this->orders->saveOrFail($order);

        $this->assertSame('pending', $this->orders->currentState($order));
        $this->assertTrue($this->orders->canTransition($order, 'pay'));
        $this->assertContains('pay', $this->orders->availableTransitions($order));

        $result = $this->orders->transition($order, 'pay');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('paid', $this->orders->currentState($order));
    }

    public function testEntityTraitExposesInspectionHelpers(): void
    {
        $order = $this->orders->newEntity(['state' => 'pending']);
        $this->orders->saveOrFail($order);

        $this->assertSame('pending', $order->currentState());
        $this->assertTrue($order->canTransition('pay'));
        $this->assertContains('pay', $order->availableTransitions());
        $this->assertTrue($order->isInState('pending'));
        $this->assertFalse($order->isFinalState());

        $this->orders->transition($order, 'pay');

        $this->assertSame('paid', $order->currentState());
        $this->assertTrue($order->isFinalState());
        $this->assertFalse($order->canTransition('pay'));
    }

    public function testEntityTraitThrowsWhenNoSourceTable(): void
    {
        $this->expectException(WorkflowException::class);

        (new TraitOrder())->currentState();
    }

    private function createRegistry(): WorkflowRegistry
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid', final: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', happy: true),
            ],
        );

        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('supports')->willReturn(true);
        $loader->method('load')->willReturn($definition);

        return new WorkflowRegistry($loader, EventManager::instance());
    }
}
