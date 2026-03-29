# Admin Dashboard

The admin UI provides a visual interface for inspecting and managing workflows.

## Accessing the Admin

Default URL: `/admin/workflow`

The admin interface requires authentication in your application. The plugin does not enforce access control - integrate with your existing auth system.

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
