# Versioning & Drift Safety

When you change a workflow definition while records already sit in various
states, those records can become **orphaned** (their stored state no longer
exists) or **stale** (their state still exists, but the definition has changed).
This page explains what the plugin does about that, and the opt-in versioning
you can enable for production workflows.

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

## Opt-in version stamping

Graceful degradation keeps you running; **versioning** lets you *detect and fix*
drift deliberately. It is off by default and adds nothing until you enable it.

Enable it on the behavior:

```php
$this->addBehavior('Workflow.Workflow', [
    'workflow' => 'order',
    'versioning' => true,
    'versionField' => 'workflow_version', // default; nullable string column
]);
```

Add the nullable column to your table (the plugin does not own your tables, so
this is yours to add — exactly like the `state` field):

```php
// in a migration
$table->addColumn('workflow_version', 'string', [
    'limit' => 16,
    'null' => true,
    'default' => null,
]);
```

With versioning on, the plugin stamps the definition's **structural hash**
(`Definition::getVersionHash()`) onto each record:

- on every successful transition, and
- when a new record is first saved (so fresh records are never left unversioned).

The hash is the authoritative drift signal — it changes exactly when states or
transitions change, even if you forget to bump the human `version` number. (The
human version integer is still recorded in the transition log.)

You then get:

```php
$behavior->getVersionStamp($entity); // e.g. '593a40ae' or null (unversioned)
$behavior->isStale($entity);         // true when the stamp != current definition
```

`isStale()` returns `false` for unversioned (`null`) records — those are reported
separately and resolved with a one-time backfill.

## Adopting it on an existing table

```bash
# 1. Add the nullable column + set versioning => true (see above)

# 2. Backfill existing records with the current version (one-time)
bin/cake workflow stamp order

# 3. See what drifted at any later point
bin/cake workflow validate order --check-data
```

> [!IMPORTANT]
> The stamp is **forward-looking from the moment you enable it**. Running
> `workflow stamp` marks every existing record as the *current* version (v1 for
> you). History before that point is not reconstructed.

## Reconciling drift

When you change a definition later, move records forward with `workflow migrate`:

```bash
# Re-stamp stale records (state still valid) to the current version,
# and map orphaned records whose state was removed/renamed:
bin/cake workflow migrate order --map old_state:new_state,legacy:pending

# Preview without writing:
bin/cake workflow migrate order --map legacy:pending --dry-run
```

- **Stale** records (valid state, old stamp) are re-stamped — no state change.
- **Orphaned** records are moved to the mapped target state, re-stamped, and each
  move is logged as a transition (`_migrate`).
- The command **refuses to run** if any orphaned state has no mapping, listing
  the offenders — so nothing is silently lost.

::: tip
For ad-hoc, per-record fixes from the browser, the admin
[Orphans view](../admin/validation.md) does the same thing interactively.
:::

## What this does not do (yet)

This model keeps a **single live definition** per workflow and resolves drift by
migrating records *forward*. It does not run multiple definition versions
concurrently (old records executing the old definition while new records use the
new one). The per-record stamp is the same primitive that capability would build
on, so adopting versioning now costs nothing toward it.
