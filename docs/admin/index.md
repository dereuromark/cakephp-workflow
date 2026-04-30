# Admin Dashboard

The admin UI provides a visual interface for inspecting and managing workflows.

## Accessing the Admin

Default URL: `/admin/workflow`

## Security: `Workflow.adminAccess` (required, default-deny)

The admin UI can rewrite workflow definitions and trigger transitions, so the
plugin **fails closed** by default. The host application MUST set
`Workflow.adminAccess` to a `Closure` that receives the current request and
returns literal `true` to grant access. Anything else (unset, non-`Closure`,
returns `false`, returns a truthy non-bool, or throws) yields a `403`.

```php
// In config/bootstrap.php (or wherever your plugin config lives):

// Example 1 — admin role check (cakephp/authentication identity):
Configure::write('Workflow.adminAccess', function (\Cake\Http\ServerRequest $request): bool {
    $identity = $request->getAttribute('identity');
    return $identity !== null && in_array('admin', (array)$identity->roles, true);
});

// Example 2 — IP allow-list for a private staging environment:
Configure::write('Workflow.adminAccess', function (\Cake\Http\ServerRequest $request): bool {
    return in_array($request->clientIp(), ['10.0.0.5', '10.0.0.6'], true);
});

// Example 3 — wide-open on local dev only (do NOT ship this to production):
if (Configure::read('debug')) {
    Configure::write('Workflow.adminAccess', fn () => true);
}
```

The gate runs in `beforeFilter` for every admin controller in the plugin and
plays nicely with the cakephp/authorization plugin (it calls
`skipAuthorization()` so the policy layer doesn't double-reject).

> **Why default-deny?** The workflow controllers extend the bare
> `Cake\Controller\Controller`, not your application's `AppController`, so
> per-controller auth wired via your AppController would never run anyway.
> The explicit gate makes that deliberate rather than implicit.

## Dashboard Overview

The main dashboard (`/admin/workflow`) shows:

| Section | Content |
|---------|---------|
| **Stats** | Total active items, transitions today, pending timeouts, orphans |
| **Workflows** | Each workflow with state counts and flags |
| **Pending Timeouts** | Due or overdue timeout transitions |
| **Recent Transitions** | Latest state changes across all workflows |

## Navigation

The sidebar provides quick access to:

- **Dashboard** - Cross-workflow overview
- **All Workflows** - List of configured workflows
- **Individual Workflows** - Per-workflow stats
- **Transitions** - Audit log of all transitions
- **Timeouts** - Pending and processed timeouts
- **Locks** - Active workflow locks
- **Orphans** - Records in invalid states
- **Designer** - Visual workflow creator

## Use Cases

### Developers

- Inspect workflow definitions
- Validate state machine graphs
- Debug transition issues
- Review guard and command setup

### Operators

- Monitor workflow health
- Identify stuck records
- Process manual transitions
- Manage timeout queue

### Support Teams

- Diagnose customer issues
- View transition history
- Fix orphaned records
- Understand entity state

## Screenshots

The dashboard provides at-a-glance metrics with color-coded status indicators:

- Green numbers indicate healthy metrics
- Yellow badges show pending action
- Red badges indicate problems needing attention
