# Persisted Transitions

Use `applyTransition()` when you want the low-level workflow state change only.
Use `transition()` when you want one higher-level call that can also save, log,
lock, and wrap the operation in a transaction.

## `applyTransition()` vs `transition()`

### Low-level: `applyTransition()`

```php
$behavior = $this->Orders->getBehavior('Workflow');

$result = $behavior->applyTransition($order, 'pay', [
    'user_id' => '42',
    'reason' => 'Payment captured',
]);

if ($result->isSuccess()) {
    $this->Orders->saveOrFail($order);
}
```

This keeps persistence orchestration in the caller.

### High-level: `transition()`

```php
$behavior = $this->Orders->getBehavior('Workflow');

$result = $behavior->transition($order, 'pay', [
    'user_id' => '42',
    'reason' => 'Payment captured',
]);
```

This is the behavior-level orchestration entry point. It temporarily enables
the persistence features for that call without changing the behavior's
long-lived configuration.

## Per-call orchestration options

```php
$behavior = $this->Orders->getBehavior('Workflow');

$result = $behavior->transition($order, 'pay', [], [
    'save' => true,
    'log' => true,
    'lock' => true,
    'transaction' => true,
]);
```

- `save`: Persist the entity after a successful transition
- `log`: Write a transition history record
- `lock`: Acquire and release a workflow lock for the entity
- `transaction`: Wrap transition, save, and logging in one DB transaction

## Behavior defaults

You can still define long-lived defaults on the behavior:

```php
$this->addBehavior('Workflow.Workflow', [
    'workflow' => 'order',
    'autoSave' => false,
    'autoLog' => false,
    'useLocking' => null,
    'useTransaction' => true,
]);
```

- `autoSave`: Save the entity after a successful `applyTransition()`
- `autoLog`: Persist a transition history record after a successful transition
- `useLocking`: `true` forces locks, `false` disables them, `null` auto-detects from the lock table
- `useTransaction`: Wrap transition, save, and logging in one DB transaction

## When to use which API

Use `applyTransition()` when:

- you already control the save transaction elsewhere
- you need a purely in-memory state change
- you want to defer persistence or logging

Use `transition()` when:

- application code should express the full workflow operation in one call
- you want a clear default path for save/log/lock orchestration
- you want to override orchestration behavior per call without mutating behavior config

## Testability hooks

`WorkflowBehavior` exposes:

- `setLogger()`
- `setLockManager()`

These exist to make the orchestration path easier to test and to allow
application-level integration when you need custom logger or lock manager
implementations.
