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

## Install Loaders

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
