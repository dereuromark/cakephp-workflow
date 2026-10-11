# Getting started

Complete [Installation](/guide/installation), including the loader configuration and plugin migrations. This example adds a workflow to an existing `Orders` table with an integer primary key.

## Generate states and a column migration

From the application root:

```bash
bin/cake workflow init order Orders --migration
bin/cake migrations migrate
```

The command creates `BaseOrderState.php`, `PendingState.php`, and `CompletedState.php` under `src/Workflow/Order/`, plus an application migration for the nullable `state` column. If the column already exists, omit `--migration` and keep its schema. The generated transition is named `completed`.

The base class identifies the table and field:

```php
namespace App\Workflow\Order;

use Workflow\Attribute\StateMachine;
use Workflow\State\AbstractState;

#[StateMachine(name: 'order', table: 'Orders', field: 'state')]
abstract class BaseOrderState extends AbstractState
{
}
```

The generated pending state declares its transition:

```php
namespace App\Workflow\Order;

use Workflow\Attribute\InitialState;
use Workflow\Attribute\Transition;

#[InitialState]
#[Transition(to: CompletedState::class, name: 'completed', happy: true)]
class PendingState extends BaseOrderState
{
}
```

## Attach the behavior

Add this inside the existing `OrdersTable::initialize()` method, after `parent::initialize($config)`. Preserve other table setup:

```php
$this->addBehavior('Workflow.Workflow', ['workflow' => 'order']);
```

Create new orders in the initial `pending` state. The engine treats a `null` or empty state as the initial state. Assign `pending` explicitly to existing rows when using state finders or optimistic locking, which compare stored values. Map established order statuses deliberately rather than overwriting them.

## Persist a transition

In an existing authorized controller action, load the order through the table and use the behavior's persisted transition method:

```php
$orders = $this->fetchTable('Orders');
$order = $orders->get($id);
$result = $orders->getBehavior('Workflow')->transition($order, 'completed', [
    'reason' => 'Fulfillment finished',
]);

if ($result->isSuccess()) {
    $this->Flash->success('Order completed');
    return $this->redirect(['action' => 'view', $order->id]);
}
$this->Flash->error('The order could not be completed');
```

Use this code in a POST action after checking access to the selected order. `transition()` saves the new state and can coordinate logging, locks, and timeouts. It returns a result for blocked or failed transitions. A plain workflow object's `apply()` changes the entity in memory and needs separate persistence; see [Persisted transitions](/integration/persisted-transitions).

Reload the order and confirm that its `state` is now `completed`. Repeating the same transition is blocked because no transition leaves the final state.

## Inspect the definition

```bash
bin/cake workflow list
bin/cake workflow show order
bin/cake workflow validate
```

The [admin interface](/admin/) is optional and requires an explicit `Workflow.adminAccess` callback. Loading the plugin alone does not grant backend access.

Continue with [attributes](/definitions/attributes), [behavior configuration](/guide/behavior), or the [view helper](/integration/view-helper).
