# Controller Component

The `Workflow.Workflow` component provides controller helpers for handling workflow transitions with standardized flash messages.

## Setup

Load the component in your controller:

```php [src/Controller/OrdersController.php]
public function initialize(): void
{
    parent::initialize();

    $this->loadComponent('Workflow.Workflow');
}
```

## Configuration

```php
$this->loadComponent('Workflow.Workflow', [
    'messages' => [
        'success' => "Transition '{transition}' applied successfully.",
        'blocked' => 'Transition blocked: {reasons}',
        'error' => 'Transition failed: {error}',
    ],
    'flashKey' => null, // Use custom flash key if needed
]);
```

## Basic Usage

### Apply a transition with flash messages

```php
public function transition(int $id): Response
{
    $this->request->allowMethod(['post']);

    $order = $this->Orders->get($id);
    $transition = $this->request->getData('transition');

    $this->Workflow->applyTransition(
        $this->Orders,
        $order,
        $transition,
        ['reason' => $this->request->getData('reason')],
    );

    return $this->redirect(['action' => 'view', $id]);
}
```

### Handle transition from form data

For the common pattern of extracting transition name and reason from request data:

```php
public function transition(int $id): Response
{
    $this->request->allowMethod(['post']);

    $order = $this->Orders->get($id);

    return $this->Workflow->handleTransition(
        $this->Orders,
        $order,
        ['action' => 'view', $id],
    );
}
```

This extracts `transition` and `reason` from the request data automatically.

## Custom Messages

Override messages per-call:

```php
$this->Workflow->applyTransition($this->Orders, $order, 'ship', [], [
    'messages' => [
        'success' => 'Order has been shipped!',
        'blocked' => 'Cannot ship: {reasons}',
    ],
]);
```

## Working with the Result

The `applyTransition()` method returns the `TransitionResult`, allowing additional logic:

```php
$result = $this->Workflow->applyTransition($this->Orders, $order, 'pay');

if ($result->isSuccess()) {
    // Send confirmation email, trigger webhooks, etc.
    $this->Orders->sendPaymentConfirmation($order);
}

return $this->redirect(['action' => 'view', $id]);
```

## Form Example

Typical form for triggering a transition:

```php
<?= $this->Form->create(null, ['url' => ['action' => 'transition', $order->id]]) ?>
<?= $this->Form->hidden('transition', ['value' => 'ship']) ?>
<?= $this->Form->control('reason', [
    'type' => 'textarea',
    'label' => 'Reason (optional)',
]) ?>
<?= $this->Form->button('Ship Order', ['class' => 'btn btn-primary']) ?>
<?= $this->Form->end() ?>
```

## Without the Component

If you prefer more control, use the workflow directly:

```php
$workflow = $this->workflowRegistry->get($order);
$result = $workflow->apply('pay', [
    'reason' => 'Payment captured',
]);

if ($result->isSuccess()) {
    $this->Flash->success('Payment recorded.');
} elseif ($result->isBlocked()) {
    $this->Flash->warning('Blocked: ' . implode(', ', $result->getBlockedBy()));
} else {
    $this->Flash->error('Error: ' . $result->getError()?->getMessage());
}
```
