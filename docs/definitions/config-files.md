# NEON and YAML

NEON and YAML definitions are useful when you want file-based workflow configuration.

## Side-by-Side Example

::: code-group

```neon [order.neon]
order:
  table: Orders
  field: state
  states:
    pending:
      initial: true
    paid:
      color: '#00AA00'
    completed:
      final: true
  transitions:
    pay:
      from: [pending]
      to: paid
      happy: true
    complete:
      from: [paid]
      to: completed
```

```yaml [order.yaml]
order:
  table: Orders
  field: state
  states:
    pending:
      initial: true
    paid:
      color: '#00AA00'
    completed:
      final: true
  transitions:
    pay:
      from: [pending]
      to: paid
      happy: true
    complete:
      from: [paid]
      to: completed
```

:::

## Native PHP (no dependency)

You can also define a whole workflow in a single native-PHP file — no extra package
required. Each file in the config path returns `[$workflowName => [...]]`:

```php [config/workflows/order.php]
<?php
return [
    'order' => [
        'table' => 'Orders',
        'field' => 'state',
        'states' => [
            'pending' => ['initial' => true],
            'paid' => ['final' => true, 'color' => '#00AA00'],
        ],
        'transitions' => [
            'pay' => ['from' => 'pending', 'to' => 'paid', 'happy' => true, 'guard' => 'App\\Workflow\\Order::ensurePayable'],
        ],
    ],
];
```

The `PhpLoader` is always active for the config path, so this works out of the box.
The structure matches the NEON/YAML format below.

## Install Loaders

NEON and YAML need an optional parser (PHP definitions do not):

```bash
composer require nette/neon
# OR
composer require symfony/yaml
```

## Configure the Path

```php [config/app.php]
'Workflow' => [
    'loader' => [
        'configPath' => CONFIG . 'workflows' . DS,
    ],
],
```

## Tradeoffs

Pros:

- easier to diff as data
- no PHP autoload concerns
- straightforward for ops-managed definitions

Cons:

- PHP callbacks still need code somewhere else
- less direct IDE refactoring support than attributes
