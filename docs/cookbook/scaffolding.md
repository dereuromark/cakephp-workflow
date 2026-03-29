# Scaffolding

The plugin now supports two scaffolding paths.

## Workflow Skeleton

Create a base workflow with initial and final states:

```bash
bin/cake workflow init order Orders
```

## Additional State Classes

If Bake is installed and loaded:

```bash
bin/cake bake workflow_state Order/Shipped --transition-to Delivered --transition-name deliver --final
```

## Why Only State Scaffolding?

The current architecture keeps most commands and guards as methods on state classes.

That makes state scaffolding the native fit today.

Standalone baked command/guard classes may be added later if the runtime evolves toward reusable handler classes.

