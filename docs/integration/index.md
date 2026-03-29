# Integration

These features help integrate workflows into your CakePHP application.

## Orchestration

- [Persisted Transitions](./persisted-transitions) - High-level `transition()` API with save, log, lock
- [Events, Logging, and Locks](./runtime) - Runtime layer features
- [Timeout Orchestration](./timeout-orchestration) - Automatic timeout scheduling

## Helpers

- [Controller Component](./component) - Flash messages and form handling
- [View Helper](./view-helper) - Diagrams, badges, and buttons in templates

## When You Need These

**Persisted Transitions** - when you want one call to handle save + log + lock

**Timeouts** - when states should auto-expire (e.g., "cancel if not paid in 24 hours")

**Controller Component** - when handling transitions from forms with standardized flash messages

**View Helper** - when rendering workflow diagrams or state badges in templates
