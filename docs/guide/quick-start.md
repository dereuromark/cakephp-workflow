# Quick Start

This example uses attribute-based definitions.

::: tip Try it live
A working sandbox app is available at <https://sandbox.dereuromark.de/workflow-sandbox> - explore the admin UI, transitions, and diagrams without installing anything.
:::

## 1. Generate a Workflow Skeleton

```bash
bin/cake workflow init order Orders
```

This creates a workflow directory in `src/Workflow/Order/` with three files:

::: code-group

```php [BaseOrderState.php]
namespace App\Workflow\Order;

use Workflow\Attribute\StateMachine;
use Workflow\State\AbstractState;

#[StateMachine(name: 'order', table: 'Orders', field: 'state')]
abstract class BaseOrderState extends AbstractState
{
}
```

```php [PendingState.php]
namespace App\Workflow\Order;

use Workflow\Attribute\InitialState;
use Workflow\Attribute\Transition;

#[InitialState]
#[Transition(to: CompletedState::class, name: 'complete', happy: true)]
class PendingState extends BaseOrderState
{
}
```

```php [CompletedState.php]
namespace App\Workflow\Order;

use Workflow\Attribute\FinalState;

#[FinalState]
class CompletedState extends BaseOrderState
{
}
```

:::

If Bake is installed, you can add more states later:

```bash
bin/cake bake workflow_state Order/Shipped --transition-to Delivered --transition-name deliver
```

See [Attributes](/definitions/attributes) for adding guards, commands, and lifecycle callbacks.

## 2. Attach the Behavior

In your table class:

```php [src/Model/Table/OrdersTable.php]
public function initialize(array $config): void
{
    parent::initialize($config);

    $this->addBehavior('Workflow.Workflow', [
        'workflow' => 'order',
    ]);
}
```

## 3. Apply Transitions

```php [src/Controller/OrdersController.php]
$order = $this->Orders->get($id);
$workflow = $this->workflowRegistry->get($order);

if ($workflow->can('complete')) {
    $result = $workflow->apply('complete', [
        'user_id' => $this->Authentication->getIdentity()->getIdentifier(),
        'reason' => 'Fulfillment finished',
    ]);

    if ($result->isSuccess()) {
        $this->Orders->saveOrFail($order);
    }
}
```

See [Behavior Integration](./behavior) for the full API.

## 4. Inspect the Workflow

CLI:

```bash
bin/cake workflow list
bin/cake workflow show order
bin/cake workflow validate
```

Admin UI:

- `/admin/workflow/workflows`
- `/admin/workflow/workflows/view/order`

## Next Steps

- [Behavior Integration](./behavior) - Full behavior API reference
- [Definitions](/definitions/) - Explore definition formats in depth
- [View Helper](/integration/view-helper) - Render diagrams, badges, and buttons in templates
