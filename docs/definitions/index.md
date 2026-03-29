# Definitions Overview

Workflows are defined using one of two formats:

| Format | Best For |
|--------|----------|
| **PHP Attributes** | Teams who prefer PHP-first, IDE refactoring support |
| **NEON/YAML** | Ops teams, file-based changes, declarative config |

Both formats resolve to the same runtime model.

## Quick Comparison

::: code-group

```php [Attributes]
#[InitialState]
#[Transition(to: PaidState::class, name: 'pay', happy: true)]
class PendingState extends BaseOrderState
{
    #[Guard('pay')]
    public function ensurePayable(): bool|string
    {
        return $this->getEntity()->total > 0
            ? true
            : 'Order total must be positive';
    }
}
```

```neon [NEON]
order:
  table: Orders
  field: state
  states:
    pending:
      initial: true
    paid:
      color: '#00AA00'
  transitions:
    pay:
      from: [pending]
      to: paid
      happy: true
```

:::

## Choosing a Format

### PHP Attributes

Use when:

- Your team prefers PHP-first definitions
- Guards and commands live naturally in state classes
- You want IDE navigation and refactoring
- Logic and state definition are tightly coupled

### NEON or YAML

Use when:

- Operations teams need easy file-based changes
- Definitions should be declarative data
- You want to review graph changes without touching PHP
- Guards/commands are minimal or external

## Format Details

- [Attributes](./attributes) - State classes with PHP 8 attributes
- [NEON and YAML](./config-files) - Config file definitions
- [Automatic Transitions](./automatic-transitions) - Conditional auto-branching

## Core Concepts

For understanding states, transitions, guards, and commands, see [Concepts](/guide/concepts).
