# Getting Started

`cakephp-workflow` is a CakePHP plugin for state-machine style workflows.

It is designed around:

- one workflow definition per business flow
- one current state field per entity
- transitions guarded by PHP logic
- optional automatic branching, logging, locking, and timeout processing

## What It Is Good At

This plugin fits best when you want:

- a clear lifecycle for records such as orders, tickets, payouts, or approvals
- CakePHP ORM integration through a behavior
- workflow inspection from an admin dashboard
- PHP-first definitions with strong editor support

## What It Is Not

The current engine is a **single-state workflow/state machine**.

That means:

- an entity is in one current state at a time
- transitions move from one state to one target state
- it is not a full multi-place workflow-net engine

For most application lifecycles this is exactly the right level of complexity.

## Typical Flow

1. Define a workflow using attributes, YAML, or NEON.
2. Attach `Workflow.Workflow` behavior to a table.
3. Use `canTransition()` and `applyTransition()` on entities.
4. Inspect workflows through the admin UI or CLI.

## Next Steps

- [Installation](./installation)
- [Quick Start](./quick-start)
- [Definitions Overview](/definitions/)
- [CLI Reference](/reference/cli)

