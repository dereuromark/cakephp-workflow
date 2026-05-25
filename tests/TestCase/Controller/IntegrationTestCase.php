<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\TestSuite\IntegrationTestTrait;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Loader\LoaderInterface;
use Workflow\Service\WorkflowRegistry;
use Workflow\Test\TestCase\DatabaseTestCase;

/**
 * Base test case for controller integration tests.
 */
abstract class IntegrationTestCase extends DatabaseTestCase
{
    use IntegrationTestTrait;

    protected WorkflowRegistry $workflowRegistry;

    public function setUp(): void
    {
        parent::setUp();

        $this->truncateTables();
        $this->setupWorkflowRegistry();
    }

    public function tearDown(): void
    {
        Configure::delete('Workflow.adminActorResolver');
        Configure::delete('Workflow.registry');
        parent::tearDown();
    }

    /**
     * Set up the workflow registry with test definitions.
     */
    protected function setupWorkflowRegistry(): void
    {
        $definitions = $this->createTestDefinitions();
        $loader = $this->createMockLoader($definitions);
        $this->workflowRegistry = new WorkflowRegistry($loader, EventManager::instance());
        Configure::write('Workflow.registry', $this->workflowRegistry);
    }

    /**
     * Create test workflow definitions.
     *
     * @return array<string, \Workflow\Engine\Definition\Definition>
     */
    protected function createTestDefinitions(): array
    {
        return [
            'order' => new Definition(
                name: 'order',
                table: 'Orders',
                field: 'state',
                states: [
                    new State('pending', initial: true),
                    new State('paid'),
                    new State('shipped'),
                    new State('completed', final: true),
                    new State('cancelled', final: true),
                ],
                transitions: [
                    new Transition('pay', ['pending'], 'paid'),
                    new Transition('ship', ['paid'], 'shipped'),
                    new Transition('complete', ['shipped'], 'completed'),
                    new Transition('cancel', ['pending', 'paid'], 'cancelled'),
                ],
            ),
            'payment' => new Definition(
                name: 'payment',
                table: 'Payments',
                field: 'status',
                states: [
                    new State('pending', initial: true),
                    new State('processed', final: true),
                    new State('failed', final: true),
                ],
                transitions: [
                    new Transition('process', ['pending'], 'processed'),
                    new Transition('fail', ['pending'], 'failed'),
                ],
            ),
        ];
    }

    /**
     * Create a mock loader with the given definitions.
     *
     * @param array<string, \Workflow\Engine\Definition\Definition> $definitions
     *
     * @return \Workflow\Loader\LoaderInterface
     */
    protected function createMockLoader(array $definitions): LoaderInterface
    {
        $loader = $this->createMock(LoaderInterface::class);

        $loader->method('supports')->willReturnCallback(
            fn (string $name): bool => isset($definitions[$name]),
        );

        $loader->method('load')->willReturnCallback(
            fn (string $name): Definition => $definitions[$name],
        );

        $loader->method('getWorkflowNames')->willReturn(array_keys($definitions));

        return $loader;
    }

    /**
     * Create a test order row and return its generated id.
     */
    protected function createOrder(string $state = 'pending', float $total = 10.0): int
    {
        $ordersTable = $this->fetchTable('Orders');
        $order = $ordersTable->saveOrFail($ordersTable->newEntity([
            'state' => $state,
            'total' => $total,
            'payment_captured' => false,
        ]));

        return (int)$order->get('id');
    }

    /**
     * Create a test payment row and return its generated id.
     */
    protected function createPayment(string $status = 'pending'): int
    {
        $paymentsTable = $this->fetchTable('Payments');
        $payment = $paymentsTable->saveOrFail($paymentsTable->newEntity([
            'status' => $status,
        ]));

        return (int)$payment->get('id');
    }
}
