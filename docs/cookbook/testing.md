# Testing workflows

Test definitions and guards without a database, then test persistence against your
application's fixtures. The plugin's `WorkflowTestTrait` provides a definition
factory and assertions for state changes.

## Engine test without fixtures

```php [tests/TestCase/Workflow/TransitionTest.php]
namespace App\Test\TestCase\Workflow;

use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Workflow\Test\WorkflowTestTrait;

class TransitionTest extends TestCase
{
    use WorkflowTestTrait;

    public function testApproval(): void
    {
        $definition = $this->createTestWorkflow();
        $entity = new Entity(['state' => 'pending']);
        $registry = $this->createTestRegistry($definition);
        $workflow = $registry->get($entity, 'test');

        $this->assertCanTransition($definition, $entity, 'approve');
        $this->assertTrue($workflow->apply('approve')->isSuccess());
        $this->assertSame('approved', $entity->get('state'));
        $this->assertCannotTransition($definition, $entity, 'approve');
    }
}
```

The factory's default states are `pending`, `approved` and final `completed`.
Its transitions are `approve` and `complete`. Pass your own `State` and
`Transition` objects to `createTestWorkflow()` for a different definition.
This test checks an in-memory entity; it does not verify database writes.

## Load an application definition

Use the same namespace as the application configuration:

```php
use Cake\Event\EventManager;
use Workflow\Loader\AttributeLoader;
use Workflow\Service\WorkflowRegistry;

$registry = new WorkflowRegistry(
    new AttributeLoader(['App\\Workflow']),
    new EventManager(),
);
$definition = $registry->getWorkflow('order');
$this->assertSame('Orders', $definition->getTable());
$this->assertSame('state', $definition->getField());
```

This loads the definition classes rather than creating a test substitute. Add
cases for each guard's permitted and blocked inputs. Keep external effects behind
services that the test can replace.

## Verify persisted transitions

After completing [Getting started](../guide/quick-start), create an application
`OrdersFixture` with a pending order. The example injects a registry directly into the behavior, so it does not depend on a previously built application container. Tests that use transition logging, locks or
timeouts also need fixtures for the corresponding plugin tables.

```php
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Workflow\Loader\AttributeLoader;
use Workflow\Service\WorkflowRegistry;

class OrdersTableWorkflowTest extends TestCase
{
    protected array $fixtures = ['app.Orders'];

    public function testCompletesOrder(): void
    {
        $registry = new WorkflowRegistry(
            new AttributeLoader(['App\\Workflow']),
            new EventManager(),
        );
        $orders = $this->getTableLocator()->get('Orders');
        $behavior = $orders->getBehavior('Workflow');
        $behavior->setConfig('registry', $registry);
        $order = $orders->get(1);

        $result = $behavior->transition($order, 'completed', [], [
            'log' => false,
            'lock' => false,
            'timeouts' => false,
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('completed', $orders->get(1)->get('state'));
    }
}
```

The transition name `completed` matches the generated beginner definition. Replace
it when testing another workflow. Ensure fixture row `1` starts in `pending`.

Add a blocked-transition test that reloads the row and confirms its state did not
change. Test authorization separately through your application's controller tests.
Changing the state directly and saving an existing entity raises
`WorkflowException` when the behavior's default `validateOnSave` is enabled.
