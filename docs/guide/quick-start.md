# Quick Start

This example uses attribute-based definitions.

## 1. Generate a Workflow Skeleton

```bash
bin/cake workflow init order Orders
```

This creates a basic workflow directory with:

- `BaseOrderState.php`
- `PendingState.php`
- `CompletedState.php`

If Bake is installed, you can add more states later:

```bash
bin/cake bake workflow_state Order/Shipped --transition-to Delivered --transition-name deliver
```

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

if ($this->Orders->canTransition($order, 'completed')) {
    $result = $this->Orders->applyTransition($order, 'completed', [
        'user_id' => $this->Authentication->getIdentity()->getIdentifier(),
        'reason' => 'Fulfillment finished',
    ]);

    if ($result->isSuccess()) {
        $this->Orders->saveOrFail($order);
    }
}
```

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

