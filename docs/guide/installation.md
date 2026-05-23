# Installation

## Requirements

- PHP 8.2+
- CakePHP 5.2+

## Composer

Install the plugin:

```bash
composer require dereuromark/cakephp-workflow
```

If you want the Bake-powered scaffolding command:

```bash
composer require --dev cakephp/bake
```

## Load the Plugin

```bash
bin/cake plugin load Workflow
```

The plugin auto-loads Bake if installed, so `bin/cake bake workflow_state` works without additional configuration.

## Migrations

Run the plugin migrations:

```bash
bin/cake migrations migrate --plugin Workflow
```

This creates three tables: `workflow_transitions`, `workflow_locks`, and
`workflow_timeouts`.

### Upgrading from 0.1.x

0.2.0 renames the polymorphic columns to the CakePHP convention:
`entity_table` → `model` and `entity_id` → `foreign_key`.

1. Run `bin/cake migrations migrate --plugin Workflow` — the `RenamePolymorphicColumns`
   migration renames the columns in place, **preserving existing data**.
2. If you passed the **`entityTable`** option when attaching the behavior, rename that
   key to **`model`**:

   ```php
   $this->addBehavior('Workflow.Workflow', [
       'workflow' => 'order',
       'model' => 'Orders', // was: 'entityTable' => 'Orders'
   ]);
   ```

   ::: warning
   If you previously set a **custom** `entityTable` (different from the table's
   registry alias) and don't rename it to `model`, the behavior falls back to the
   alias and will read/write a different polymorphic value than what's already stored.
   Apps that never set `entityTable` (the default) need no code change.
   :::

### Schema reference

A merged snapshot of the full schema (all migrations combined) is shipped as DBML at
[`resources/schema/schema.dbml`](https://github.com/dereuromark/cakephp-workflow/blob/master/resources/schema/schema.dbml).
Paste it into [dbdiagram.io](https://dbdiagram.io) (or any [DBML](https://dbml.dbdiagram.io/docs) tool)
to view the tables, columns, and indexes as a diagram. It's hand-maintained — keep it in
sync when a migration changes these tables.

### Entity id type (integer or UUID primary keys)

The workflow tables (`workflow_transitions`, `workflow_locks`, `workflow_timeouts`)
reference your entities through a generic `foreign_key` column. It is **not** a real
foreign key — each row can point at a different table — so it cannot be constrained.

`foreign_key` is **polymorphic** (paired with `model`). Its column type follows the
shared `Polymorphic.type` config key (the same convention used across the plugin family),
defaulting to `integer`. Set it **before** running the migration to change it:

```php [config/app.php]
'Polymorphic' => [
    'type' => 'biginteger', // integer (default) | biginteger | uuid | binaryuuid
],
```

For `integer`/`biginteger` the column's signedness follows your
`Migrations.unsigned_primary_keys` setting, so it lines up with how your application's
primary keys are defined (signed by default; unsigned only takes effect on MySQL).

#### Using UUID / char primary keys

::: tip Fully supported
UUID (or other string/char) primary keys work out of the box. The behavior always
passes the id through as a string, so **no application code changes are needed** — only
the `foreign_key` column type.
:::

The simplest way is to set the column type **before** running the migration:

```php [config/app.php]
'Polymorphic' => [
    'type' => 'uuid', // or 'binaryuuid'
],
```

```bash
bin/cake migrations migrate --plugin Workflow
```

That's it — transitions, locks, and timeouts now store and look up your UUID ids
unchanged.

If you already ran the migration with an integer type, widen `foreign_key` afterwards with
a migration in your app instead:

```php [config/Migrations/XXXXXXXXXXXXXX_WorkflowEntityIdToUuid.php]
use Migrations\BaseMigration;

class WorkflowEntityIdToUuid extends BaseMigration
{
    public function change(): void
    {
        foreach (['workflow_transitions', 'workflow_locks', 'workflow_timeouts'] as $table) {
            $this->table($table)
                ->changeColumn('foreign_key', 'string', ['limit' => 36, 'null' => false])
                ->update();
        }
    }
}
```

::: warning One id type per install
A single installation should use one id type consistently. Mixing integer- and
UUID-keyed tables under the same workflow tables is not supported.
:::

## Base Configuration

Configure the plugin in `config/app.php`:

```php [config/app.php]
'Workflow' => [
    'loader' => [
        'namespaces' => [
            'App\\Workflow',
        ],
        'configPath' => CONFIG . 'workflows' . DS,
    ],
    'logging' => true,
    'locking' => true,
    'timeouts' => true,
    'lockDuration' => 30,
],
```

## Loader Strategy

You can enable one or more definition sources:

- attributes: add namespaces under `Workflow.loader.namespaces`
- NEON: install `nette/neon`
- YAML: install `symfony/yaml`

When multiple loaders are enabled, the plugin combines them through a chain loader.
