# Behavior Integration

The main application API is the `Workflow.Workflow` ORM behavior.

## Configuration

```php
$this->addBehavior('Workflow.Workflow', [
    'workflow' => 'order',
    'validateOnSave' => true,
    'autoSave' => false,
    'autoLog' => false,
    'entityTable' => 'Orders',
]);
```

## Core Methods

### Check if a transition is possible

```php
$this->Orders->canTransition($order, 'pay');
```

### Apply a transition

```php
$result = $this->Orders->applyTransition($order, 'pay', [
    'user_id' => '42',
    'reason' => 'Payment captured',
]);
```

### Get available transitions

```php
$transitions = $this->Orders->getAvailableTransitions($order);
```

### Read the current state

```php
$state = $this->Orders->getCurrentState($order);
```

## Save Protection

When `validateOnSave` is enabled, direct state mutation is rejected.

This prevents code from bypassing the workflow engine by changing the state field manually and calling `save()`.

For new entities:

- empty state is allowed
- the initial state is allowed
- any other state is rejected

For existing entities:

- state changes must go through `applyTransition()`

