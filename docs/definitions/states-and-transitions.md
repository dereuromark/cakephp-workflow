# States and Transitions

## States

A state can define:

- `name`
- `label`
- `color`
- `initial`
- `final`
- `failed`
- `flags`
- `onEnter`
- `onExit`
- required-reason transitions

## Transitions

A transition can define:

- `name`
- `from`
- `to`
- `happy`
- guards
- commands
- condition
- automatic mode

## Happy Path

The analyzer uses `happy` transitions to reason about the preferred path through a workflow.

This is useful for:

- design review
- validation
- reporting

## Flags

Flags are lightweight state annotations.

Use them for tags such as:

- `requires_attention`
- `billable`
- `archived`

They are intentionally simple and not a full metadata system.

