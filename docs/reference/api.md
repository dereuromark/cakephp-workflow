# API Reference

This page is intentionally high-level and focuses on the public surface most users touch first.

## `WorkflowBehavior`

Primary methods:

- `canTransition(EntityInterface $entity, string $transition, array $context = []): bool`
- `applyTransition(EntityInterface $entity, string $transition, array $context = []): TransitionResult`
- `getAvailableTransitions(EntityInterface $entity): array`
- `getCurrentState(EntityInterface $entity): string`
- `isInState(EntityInterface $entity, string $state): bool`
- `getWorkflowDefinition(): Definition`

## `StateMachineEngine`

Core responsibilities:

- resolve current state
- validate transition applicability
- evaluate guards
- execute commands and lifecycle callbacks
- dispatch workflow events
- process automatic transitions

## `TransitionResult`

Represents:

- success
- blocked transition
- transition error

Important values:

- from state
- target state
- blocked reasons
- throwable for errors

## `WorkflowRegistry`

Registry responsibilities:

- load definitions by name
- return workflow names
- provide engine instances

## `WorkflowHelper`

Useful template helpers:

- Mermaid diagram rendering
- state badge rendering
- transition button rendering

