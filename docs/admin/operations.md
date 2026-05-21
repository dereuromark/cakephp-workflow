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

The admin timeouts screen supports:

- executing one timeout immediately
- cancelling one timeout without executing it
- retrying a processed timeout
- bulk executing selected pending timeouts
- bulk cancelling selected pending timeouts
- executing all currently due pending timeouts

Manual admin execution writes persisted transition rows with:

- `user_id` from the current identity or legacy `Auth.User.id` session fallback
- `reason` when supplied
- `context.admin_action = true`
- `context.client_ip`

## Locks

Locks prevent concurrent mutation of the same item while workflow actions are running.

Use the admin lock view to inspect:

- stale locks
- lock holders
- expiration timing

Operationally, locks matter most when:

- background workers run transitions
- multiple admins can act on the same record

## Transition Audit Views

The transitions admin screen supports filtering by:

- workflow
- entity id
- status
- actor id
- admin-vs-runtime origin
- created date range

Each transition also has a detail view showing:

- reason
- actor display/link
- client IP
- runtime metadata
- blocked/error payloads
- full stored context
