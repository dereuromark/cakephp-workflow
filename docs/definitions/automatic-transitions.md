# Automatic Transitions

Automatic transitions let the engine branch without an explicit user-triggered event.

## What They Do

When the entity enters a state, the engine can immediately evaluate outgoing automatic transitions.

This is useful for:

- routing logic
- conditional branching
- auto-completion flows

## How Selection Works

The engine evaluates automatic transitions in order:

- the first matching condition wins
- a transition without a condition acts as a fallback branch

To avoid loops, the engine enforces a maximum automatic transition count.

## Important Limitation

Automatic transitions are still part of a single-state model.

They do **not** mean:

- parallel active states
- fork/join workflow nets
- multiple simultaneous markings

So they help with branching, but they do not turn the engine into a multi-place workflow system.

