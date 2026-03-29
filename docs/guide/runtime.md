# Events, Logging, and Locks

The runtime layer provides more than just state mutation.

## Workflow Events

The engine dispatches:

- `Workflow.beforeTransition`
- `Workflow.afterTransition`
- `Workflow.transitionBlocked`
- `Workflow.transitionError`

These events carry:

- the workflow definition
- the entity
- transition name
- source state
- optional target state
- context payload

## Transition Logging

`TransitionLogger` records successful transitions in the `workflow_transitions` table.

Typical stored data:

- workflow name
- entity table and id
- transition name
- from/to states
- user id
- reason
- JSON-encoded context

## Locking

`LockManager` provides short-lived item locks using `workflow_locks`.

Use it when:

- multiple operators may act on the same workflow item
- background processing and UI actions could race

## Timeouts

Timeouts are stored in `workflow_timeouts` and processed through:

```bash
bin/cake workflow timeouts
```

This command:

- finds due transitions
- reloads the affected entity
- verifies the entity is still in the expected state
- applies the transition if still valid

