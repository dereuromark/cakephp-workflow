# CLI Reference

## Workflow Commands

### `workflow init`

Scaffold an attribute-based workflow skeleton.

```bash
bin/cake workflow init order Orders
```

### `workflow list`

List configured workflows.

```bash
bin/cake workflow list
bin/cake workflow list --verbose
```

### `workflow show`

Show one workflow in detail.

```bash
bin/cake workflow show order
bin/cake workflow show order --mermaid
```

The `--mermaid` option prints a [Mermaid](https://mermaid.js.org/) `flowchart` definition you can paste into Markdown, an issue, or this documentation. It renders like this:

```mermaid
flowchart TD
    pending([Pending])
    paid([Paid])
    shipped([Shipped])
    completed([Completed])
    cancelled([Cancelled])
    pending -->|pay| paid
    paid -->|ship| shipped
    shipped -->|complete| completed
    pending -->|cancel| cancelled
    classDef initial fill:#f5f5f5,stroke:#9e9e9e,stroke-width:2px
    classDef final fill:#e8f5e9,stroke:#4caf50,stroke-width:2px
    classDef failed fill:#ffebee,stroke:#f44336,stroke-width:2px
    class pending initial
    class completed final
    class cancelled failed
    linkStyle 0,1,2 stroke:#2e7d32,stroke-width:2px
```

### `workflow apply`

Apply a transition to a single record from the command line — handy for ops, debugging, and scripted/cron-driven transitions.

```bash
bin/cake workflow apply order 42 pay
bin/cake workflow apply order 42 pay --reason "manual capture" --user 7
bin/cake workflow apply order 42 pay --dry-run
```

It loads the record, runs the transition through the behavior (save + log, plus lock when enabled), and prints the outcome. `--dry-run` only checks whether the transition is allowed. Exit code is non-zero when the transition is blocked, locked, errored, or the record is missing.

### `workflow history`

Show the transition audit trail for a single record.

```bash
bin/cake workflow history order 42
bin/cake workflow history order 42 --success   # successful transitions only
bin/cake workflow history order 42 --limit 20
```

```
+------------------+------------+-----------+-----------+---------+------+--------+
| When             | Transition | From      | To        | Status  | User | Reason |
+------------------+------------+-----------+-----------+---------+------+--------+
| 5/22/26, 4:42 PM | verify     | generated | verified  | success | 7    | qa ok  |
| 5/22/26, 4:42 PM | generate   | preflight | generated | success |      |        |
+------------------+------------+-----------+-----------+---------+------+--------+
```

### `workflow validate`

Validate one or more workflows and surface analyzer findings. With `--check-data`
it also reports records sitting in states that no longer exist (orphaned records).

```bash
bin/cake workflow validate
bin/cake workflow validate order --check-data
```

### `workflow migrate`

Move orphaned records (whose state was removed/renamed) forward to a valid state.
Refuses to run if any orphaned state lacks a mapping; runs atomically and logs
each move. See [Drift Safety](../guide/versioning.md).

```bash
bin/cake workflow migrate order --map old_state:new_state,legacy:pending
bin/cake workflow migrate order --map legacy:pending --dry-run
```

### `workflow timeouts`

Process due timeouts.

```bash
bin/cake workflow timeouts
bin/cake workflow timeouts --dry-run
bin/cake workflow timeouts --limit 100
```

## Bake Commands

### `bake workflow_state`

Requires `cakephp/bake` and the `Bake` plugin.

```bash
bin/cake bake workflow_state Order/Shipped
bin/cake bake workflow_state Order/Shipped --final
bin/cake bake workflow_state Order/Shipped --transition-to Delivered --transition-name deliver
```

