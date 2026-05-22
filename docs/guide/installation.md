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

### Entity id type (UUID primary keys)

The workflow tables (`workflow_transitions`, `workflow_locks`, `workflow_timeouts`)
reference your entities through a generic `entity_id` column. It is **not** a real
foreign key — each row can point at a different table — so it cannot be constrained.

By default `entity_id` is a `biginteger`, which matches the common case of integer
primary keys and keeps the indexes compact.

If your workflow-enabled tables use **UUID / char primary keys**, widen the column in
your own app migration before storing anything (an integer column cannot hold a UUID):

```php [config/Migrations/XXXXXXXXXXXXXX_WorkflowEntityIdToUuid.php]
use Migrations\BaseMigration;

class WorkflowEntityIdToUuid extends BaseMigration
{
    public function change(): void
    {
        foreach (['workflow_transitions', 'workflow_locks', 'workflow_timeouts'] as $table) {
            $this->table($table)
                ->changeColumn('entity_id', 'string', ['limit' => 36, 'null' => false])
                ->update();
        }
    }
}
```

The behavior always passes the id through as a string, so no code changes are needed —
only the column type. Note that a single installation should use one id type
consistently; mixing integer- and UUID-keyed tables under the same workflow tables is
not supported.

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
