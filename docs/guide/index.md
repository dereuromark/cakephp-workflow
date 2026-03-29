# Getting Started

`cakephp-workflow` is a CakePHP plugin for state-machine style workflows.

## What It Does

The plugin tracks where records are in their lifecycle:

- An order: `pending` → `paid` → `shipped` → `completed`
- A ticket: `open` → `in_progress` → `resolved`
- An approval: `draft` → `submitted` → `approved`

At any moment, a record is in exactly **one state**. You control how it moves between states.

## Core Features

| Feature | Description |
|---------|-------------|
| **PHP 8 Attributes** | Define workflows in PHP classes |
| **NEON/YAML** | Or use config files |
| **Guards** | Conditional transitions |
| **Commands** | Actions on transitions |
| **ORM Behavior** | CakePHP table integration |
| **Admin UI** | Visual dashboard |
| **CLI Tools** | Management commands |

## Typical Flow

```
1. Define a workflow (attributes or config file)
2. Attach behavior to your table
3. Use workflow.can() and workflow.apply()
4. Inspect via admin UI or CLI
```

## What It Is Good For

- Clear lifecycle for records (orders, tickets, payouts, approvals)
- CakePHP ORM integration through a behavior
- PHP-first definitions with editor support
- Workflow inspection from an admin dashboard

## What It Is Not

This is a **single-state workflow engine**:

- An entity is in one state at a time
- Transitions move from one state to one target state
- It is not a multi-place workflow-net engine

For most application lifecycles, this is exactly the right level of complexity.

## Quick Example

```php
// Get a workflow for an entity
$workflow = $this->workflowRegistry->get($order);

// Check and apply a transition
if ($workflow->can('pay')) {
    $result = $workflow->apply('pay');
    if ($result->isSuccess()) {
        $this->Orders->save($order);
    }
}
```

## Next Steps

1. [Installation](./installation) - Get the plugin running
2. [Concepts](./concepts) - Understand states, transitions, guards
3. [Quick Start](./quick-start) - Build your first workflow
4. [Behavior Integration](./behavior) - Full API reference
