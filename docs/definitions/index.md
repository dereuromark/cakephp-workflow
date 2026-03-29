# Definitions Overview

The plugin supports multiple workflow definition styles:

- PHP attributes
- NEON/YAML

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

## Shared Core Model

All formats resolve into the same runtime model:

- `Definition`
- `State`
- `Transition`

That means features like validation, diagrams, behavior integration, and transition execution work the same way after loading.
