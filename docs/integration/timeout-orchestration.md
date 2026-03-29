# Timeout Orchestration

Persisted transitions can keep `workflow_timeouts` in sync automatically.

This orchestration runs when you use the high-level behavior API:

```php
$behavior = $this->Orders->getBehavior('Workflow');
$result = $behavior->transition($order, 'pay');
```

When the transition succeeds and the entity is saved, the runtime will:

- mark pending timeouts for that entity as processed
- inspect the target state's timeout definitions
- create new timeout rows for the target state

This keeps the timeout queue aligned with the entity's persisted state.

## What Gets Orchestrated

Timeout orchestration currently runs in these persisted paths:

- `WorkflowBehavior::transition()`
- the timeout processing command
- manual timeout execution in the admin UI

`applyTransition()` stays in-memory only. It does not schedule or cancel timeouts.

## Behavior Options

`transition()` accepts the same persisted options as the save/log/lock flow, with timeout control added:

```php
$behavior = $this->Orders->getBehavior('Workflow');
$result = $behavior->transition($order, 'pay', [], [
    'save' => true,
    'log' => true,
    'lock' => null,
    'timeouts' => null,
    'transaction' => true,
]);
```

- `timeouts: true` forces timeout sync on
- `timeouts: false` disables it for the call
- `timeouts: null` uses the default runtime behavior

The default behavior is:

- only on persisted transitions (`save: true`)
- only if `Workflow.timeouts` is enabled
- only if the `workflow_timeouts` table exists

## Defining Timeouts

Timeouts are attached to states, not transitions.

Attribute example:

```php
#[Timeout('PT30M', 'expire')]
class PendingState extends BaseOrderState
{
}
```

YAML example:

```yaml
pending:
  initial: true
  timeouts:
    - after: PT30M
      transition: expire
```

Supported duration formats:

- ISO-8601 intervals like `PT30M` or `P1D`
- relative date strings accepted by PHP like `2 hours`

## Operational Notes

- scheduling requires a persisted entity id
- timeout rows are marked as processed instead of deleted
- entering a state without timeout definitions still clears older pending timeouts for that entity
- chained delayed flows work because timeout-triggered transitions reschedule the next state's timeouts
