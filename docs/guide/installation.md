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
