# Definitions Overview

The plugin supports multiple workflow definition styles:

- PHP attributes (recommended)
- NEON/YAML config files

## Choosing a Format

### Attributes

Best when:

- your team prefers PHP-first definitions
- guards and commands live naturally next to states
- you want refactoring support from your IDE

### NEON or YAML

Best when:

- operations teams need easy file-based changes
- definitions should be more declarative
- you want to review graph changes without touching class files

## Core Concepts

All formats resolve into the same runtime model. Understanding these concepts helps regardless of which format you choose.

### States

A state represents a point in your entity's lifecycle. States can define:

| Property | Description |
|----------|-------------|
| `name` | Identifier used in code and database |
| `label` | Human-readable display name |
| `color` | Hex color for diagrams and badges (e.g., `#00AA00`) |
| `initial` | Starting state for new entities (exactly one per workflow) |
| `final` | Terminal state - no outgoing transitions allowed |
| `failed` | Error/failure state (implies final) |
| `flags` | Custom metadata tags for querying (e.g., `done`, `billable`) |
| `onEnter` | Callback when entering this state |
| `onExit` | Callback when leaving this state |
| `requireReason` | Require a reason when entering this state |

### Transitions

A transition moves an entity from one state to another. Transitions can define:

| Property | Description |
|----------|-------------|
| `name` | Identifier used in code (e.g., `pay`, `approve`) |
| `from` | Source state(s) - can be multiple |
| `to` | Target state - exactly one |
| `happy` | Mark as part of the preferred "happy path" |
| `guards` | Conditions that must pass for the transition to apply |
| `commands` | Actions executed when the transition succeeds |
| `automatic` | Trigger automatically when guards pass |
| `timeout` | Trigger automatically after a duration |

### Guards

Guards validate whether a transition is allowed. They return `true` to allow, or a string message explaining why the transition is blocked.

Multiple guards for the same transition are evaluated in sequence. If any guard returns a blocking message, the transition is rejected.

### Commands

Commands execute side effects when a transition succeeds. They run after guards pass but before the entity is saved. Use them to modify the entity or trigger external actions.

### Happy Path

The `happy` flag marks transitions that represent the ideal flow through your workflow. This is used by:

- Mermaid diagrams (highlighted with thicker lines)
- Validation (warnings if happy path doesn't reach a final state)
- Reporting and analytics

### Flags

Flags are lightweight state annotations for querying. Use them for tags like:

- `done` - work is complete
- `billable` - can be invoiced
- `requires_attention` - needs human review

Query entities by flag using the behavior's custom finders:

```php
$doneOrders = $this->Orders->find('withFlag', flag: 'done')->toArray();
```

## Next Steps

- [PHP Attributes](./attributes) - Define workflows in PHP classes
- [NEON and YAML](./config-files) - Define workflows in config files
- [Automatic Transitions](./automatic-transitions) - Timeouts and auto-transitions
