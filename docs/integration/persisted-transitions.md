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

When logging is enabled, the stored transition row persists:

- the transition status (`success`, `blocked`, `locked`, `error`)
- the optional `user_id`
- the optional `reason`
- structured `context` JSON, including runtime metadata for guards/commands/locks

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

## Transaction Safety

::: warning Important
The `applyTransition()` / `apply()` methods only change the entity **in memory**. The database state is not modified until you call `save()`.

If save fails after apply succeeds, the entity object will have the new state but the database will have the old state. This is by design—it lets you control persistence—but you must handle failures appropriately.
:::

For atomic operations, use `transition()` with `transaction: true` (the default). This wraps apply + save + log in a single database transaction. If any step fails, everything rolls back.

## When to use which API

Use `applyTransition()` / `apply()` when:

- you already control the save transaction elsewhere
- you need a purely in-memory state change
- you want to defer persistence or logging
- you handle save failures and re-fetch the entity if needed

Use `transition()` when:

- application code should express the full workflow operation in one call
- you want atomicity: apply + save + log in one transaction
- you want a clear default path for save/log/lock orchestration
- you want to override orchestration behavior per call without mutating behavior config

## Testability hooks

`WorkflowBehavior` exposes:

- `setLogger()`
- `setLockManager()`

These exist to make the orchestration path easier to test and to allow
application-level integration when you need custom logger or lock manager
implementations.
