# Testing Workflows

Testing workflows involves validating definitions, engine behavior, and ORM integration.

## Definition Tests

Validate that your workflow definitions are correct:

```php
use Cake\TestSuite\TestCase;
use Workflow\Engine\WorkflowRegistry;

class OrderWorkflowDefinitionTest extends TestCase
{
    public function testDefinitionLoads(): void
    {
        $registry = new WorkflowRegistry();
        $definition = $registry->get('order');

        $this->assertSame('order', $definition->getName());
        $this->assertSame('Orders', $definition->getTable());
        $this->assertSame('state', $definition->getField());
    }

    public function testHasExpectedStates(): void
    {
        $registry = new WorkflowRegistry();
        $definition = $registry->get('order');

        $stateNames = array_map(
            fn($s) => $s->getName(),
            $definition->getStates(),
        );

        $this->assertContains('pending', $stateNames);
        $this->assertContains('paid', $stateNames);
        $this->assertContains('completed', $stateNames);
    }

    public function testHasValidInitialState(): void
    {
        $registry = new WorkflowRegistry();
        $definition = $registry->get('order');

        $initial = $definition->getInitialState();

        $this->assertNotNull($initial);
        $this->assertSame('pending', $initial->getName());
    }
}
```

## Engine Tests

Test transition logic directly:

```php
use Cake\TestSuite\TestCase;
use Workflow\Engine\StateMachineEngine;
use Workflow\Engine\WorkflowRegistry;

class OrderTransitionTest extends TestCase
{
    protected array $fixtures = ['app.Orders'];

    public function testCanTransitionFromPending(): void
    {
        $order = $this->getTableLocator()->get('Orders')->get(1);
        $order->state = 'pending';
        $order->total = 100.00;

        $registry = new WorkflowRegistry();
        $engine = $registry->getEngine('order');

        $this->assertTrue($engine->canTransition($order, 'pay'));
        $this->assertTrue($engine->canTransition($order, 'cancel'));
        $this->assertFalse($engine->canTransition($order, 'ship'));
    }

    public function testPayGuardBlocksZeroTotal(): void
    {
        $order = $this->getTableLocator()->get('Orders')->get(1);
        $order->state = 'pending';
        $order->total = 0;

        $registry = new WorkflowRegistry();
        $engine = $registry->getEngine('order');

        $this->assertFalse($engine->canTransition($order, 'pay'));
    }

    public function testApplyTransitionChangesState(): void
    {
        $order = $this->getTableLocator()->get('Orders')->get(1);
        $order->state = 'pending';
        $order->total = 100.00;

        $registry = new WorkflowRegistry();
        $engine = $registry->getEngine('order');

        $result = $engine->applyTransition($order, 'pay');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('paid', $order->state);
    }
}
```

## Behavior Tests

Test through the ORM behavior:

```php
use Cake\TestSuite\TestCase;

class OrdersTableWorkflowTest extends TestCase
{
    protected array $fixtures = ['app.Orders'];

    public function testCanTransition(): void
    {
        $orders = $this->getTableLocator()->get('Orders');
        $order = $orders->get(1);
        $order->state = 'pending';
        $order->total = 100.00;

        $this->assertTrue($orders->canTransition($order, 'pay'));
    }

    public function testApplyTransitionSucceeds(): void
    {
        $orders = $this->getTableLocator()->get('Orders');
        $order = $orders->get(1);
        $order->state = 'pending';
        $order->total = 100.00;

        $result = $orders->applyTransition($order, 'pay', [
            'user_id' => 1,
            'reason' => 'Test payment',
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('paid', $order->state);
    }

    public function testApplyTransitionBlockedByGuard(): void
    {
        $orders = $this->getTableLocator()->get('Orders');
        $order = $orders->get(1);
        $order->state = 'pending';
        $order->total = 0;

        $result = $orders->applyTransition($order, 'pay');

        $this->assertTrue($result->isBlocked());
        $this->assertNotEmpty($result->getBlockedBy());
    }

    public function testSaveProtectionRejectsDirect(): void
    {
        $orders = $this->getTableLocator()->get('Orders');
        $order = $orders->get(1);
        $order->state = 'pending';

        // Try to change state directly
        $order->state = 'completed';
        $result = $orders->save($order);

        $this->assertFalse($result);
        $this->assertNotEmpty($order->getError('state'));
    }
}
```

## Custom Finders

Test finder queries:

```php
public function testFindWithFlag(): void
{
    $orders = $this->getTableLocator()->get('Orders');

    $done = $orders->find('withFlag', flag: 'done')->toArray();
    $notDone = $orders->find('withoutFlag', flag: 'done')->toArray();

    $this->assertNotEmpty($done);
    $this->assertNotEmpty($notDone);
}

public function testFindInFinalState(): void
{
    $orders = $this->getTableLocator()->get('Orders');

    $final = $orders->find('inFinalState')->toArray();
    $active = $orders->find('notInFinalState')->toArray();

    foreach ($final as $order) {
        $this->assertTrue($orders->isFinal($order));
    }
}
```

## Testing Tips

1. **Use fixture factories** - Create entities in specific states
2. **Test guards independently** - Verify blocking reasons
3. **Test commands** - Assert entity changes after transitions
4. **Test edge cases** - Invalid transitions, null entities, concurrent access
5. **Mock external services** - Email, webhooks in commands
