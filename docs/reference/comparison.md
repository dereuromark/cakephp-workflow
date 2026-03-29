# Comparison and Gaps

This plugin is already stronger than older CakePHP workflow packages in several areas:

- attribute-first definitions
- Cake ORM behavior integration
- Mermaid diagrams without GraphViz
- built-in workflow analyzer and admin UI

## Compared with `cakephp-statemachine`

This package has the cleaner modern developer experience.

The older package still has some ideas worth studying:

- versioned process files
- more explicit batch/event trigger APIs

## Compared with Symfony Workflow

Symfony remains ahead in:

- multi-place workflow support
- marking-store abstraction
- metadata system
- broader event semantics

This plugin remains easier to adopt inside a CakePHP app.

## Current Important Gaps

- no multi-place workflow net support
- no general metadata bag on definitions, states, and transitions
- no complete batch orchestration layer

### Versioning Status

Basic versioning is supported:
- Version number on definitions (stored and logged)
- Version displayed in admin UI and tracked in transition history

Not yet implemented:
- Version migration tooling (migrate entities between workflow versions)
- Running multiple versions concurrently
- Version comparison/diff tools
- State mapping between versions when state names change
