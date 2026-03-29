# Testing Workflows

The plugin includes a `WorkflowTestTrait` and focused engine tests.

## Recommended Test Levels

### Definition tests

Validate that loaders produce the graph you expect.

### Engine tests

Test:

- allowed transitions
- blocked transitions
- callback execution
- automatic transitions
- failure rollback behavior

### Behavior tests

Test ORM integration:

- `canTransition()`
- `applyTransition()`
- save protection

## What to Assert

- current state before and after transition
- blocked reasons
- entity side effects
- transition result success/error
- logs or timeout side effects where relevant

