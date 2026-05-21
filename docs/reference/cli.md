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

### `workflow validate`

Validate one or more workflows and surface analyzer findings.

```bash
bin/cake workflow validate
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

