# Timeouts and Locks

## Timeouts

Timeout operations let workflows progress after a delay.

Examples:

- auto-close stale drafts
- cancel unpaid orders
- escalate untouched tickets

CLI:

```bash
bin/cake workflow timeouts
bin/cake workflow timeouts --dry-run
bin/cake workflow timeouts --limit 50
```

## Locks

Locks prevent concurrent mutation of the same item while workflow actions are running.

Use the admin lock view to inspect:

- stale locks
- lock holders
- expiration timing

Operationally, locks matter most when:

- background workers run transitions
- multiple admins can act on the same record

