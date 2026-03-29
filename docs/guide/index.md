# Getting Started

`cakephp-workflow` is a CakePHP plugin for state-machine style workflows.

It is designed around:

- one workflow definition per business flow
- one current state field per entity
- transitions guarded by PHP logic
- optional automatic branching, logging, locking, and timeout processing

## Features

- **PHP 8 Attributes**: Define workflows declaratively using modern PHP
- **NEON/YAML Support**: Alternative configuration via NEON or YAML files
- **State Types**: Initial, final, and failed state types
- **Guards**: Conditional transitions with guard methods
- **Commands**: Execute actions on state transitions
- **Lifecycle Callbacks**: OnEnter and OnExit hooks for states
- **Happy Path**: Visual emphasis on primary workflow paths
- **State Flags**: Custom metadata on states for querying
- **Audit Trail**: Full transition logging with user tracking
- **Locking**: Prevent concurrent transitions
- **Timeouts**: Automatic time-based transitions
- **Admin UI**: Visual dashboard with Mermaid.js diagrams
- **CLI Tools**: Workflow management and validation commands
- **Validation**: Detect unreachable states and dead ends

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

