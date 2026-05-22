# Drift Safety

When you change a workflow definition while records already exist, some records
can be left in a state that no longer exists — they become **orphaned**. This
page explains what the plugin does about that. It works out of the box: there is
nothing to enable and no schema to add.

## Graceful degradation (always on)

State is stored as a plain string on your entity (e.g. `articles.state`), and
definitions are loaded fresh from your source files. If you remove or rename a
state, any record still in the old state is orphaned.

The plugin degrades gracefully instead of crashing:

- Reading or displaying an orphaned state returns a neutral "unknown" state
  (gray badge, not final, no available transitions) — the admin UI and your
  templates keep working.
- `WorkflowHelper::stateBadge()`, `getStateColor()`, `WorkflowBehavior::isFinal()`
  and `hasFlag()` never throw on an orphaned value.
- Trying to transition an orphaned record returns a **blocked** result with a
  clear message, rather than throwing.

::: info
This is unconditional and free — it only ever does anything when a record is
actually in an undefined state.
:::

## Finding orphaned records

Records whose stored state is no longer defined are detected by comparing the
state against the current definition — no version tracking required.

In the admin UI, the **Orphans** view (`/admin/workflow/orphans`) lists orphaned
records across all workflows, with a live count badge in the sidebar.

From the CLI:

```bash
bin/cake workflow validate order --check-data
```

reports any records sitting in states that no longer exist.

## Fixing orphaned records

Move orphaned records to a valid state, either interactively in the Orphans view
or headlessly with the migrate command:

```bash
# Map each orphaned state to a valid target; refuses to run if any orphaned
# state is left unmapped, so nothing is silently lost:
bin/cake workflow migrate order --map old_state:new_state,legacy:pending

# Preview without writing:
bin/cake workflow migrate order --map legacy:pending --dry-run
```

Each move is logged as a transition (`_migrate`) and, when state timeouts are in
use, the target state's timeouts are scheduled for the moved record. The batch
runs in a single transaction, so a failure rolls the whole run back.

> [!NOTE]
> The migrate command assumes the audit (`workflow_transitions`) and timeout
> (`workflow_timeouts`) tables share the entity table's database connection, as
> the rest of the plugin does. On a single-connection app (the common case) the
> rollback guarantee holds.

## On workflow versions

A definition carries a `version` number (default `1`) that you bump when making
breaking changes; it is recorded on every transition in
`workflow_transitions.workflow_version` for auditing, and shown by
`bin/cake workflow show`. Records do not carry a per-record version — drift is
handled by detecting and migrating orphaned records as above, which needs no
extra column or configuration.

Running multiple definition versions concurrently (old records executing the old
definition while new records use the new one) is not supported.
