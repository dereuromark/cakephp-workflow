# Validation and Orphans

The admin provides tools to identify workflow problems.

## Workflow Validation

Access via CLI or admin UI:

```bash
bin/cake workflow validate
```

The validator checks for:

| Issue | Severity | Description |
|-------|----------|-------------|
| No initial state | Error | Workflow must have exactly one initial state |
| Multiple initials | Error | Only one initial state allowed |
| Unreachable states | Warning | States with no incoming transitions (except initial) |
| Dead ends | Warning | Non-final states with no outgoing transitions |
| Happy path incomplete | Info | Happy path doesn't reach a final state |
| Missing transitions | Warning | Defined transitions reference unknown states |
| Duplicate transitions | Warning | Same transition name defined multiple times |

## Orphaned Records

Orphans are records whose current state doesn't exist in the workflow definition.

Common causes:

- State renamed or removed from definition
- Data migration issues
- Direct database writes bypassing the workflow
- Loader misconfiguration

### Finding Orphans

The admin orphan view (`/admin/workflow/orphans`) lists:

- Entity table and ID
- Current state value (invalid)
- Workflow name
- Suggested actions

### Fixing Orphans

Options:

1. **Update definition** - Add the missing state back
2. **Manual fix** - Update the record to a valid state via admin
3. **Bulk remediation** - Use admin tools to transition multiple records

::: warning
Bulk fixes bypass guards and commands. Use carefully.
:::

### Prevention

Enable `validateOnSave` in the behavior to prevent direct state mutations:

```php
$this->addBehavior('Workflow.Workflow', [
    'workflow' => 'order',
    'validateOnSave' => true,
]);
```

This rejects any `save()` that tries to change the state field directly.
