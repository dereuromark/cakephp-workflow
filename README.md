# CakePHP Workflow Plugin

[![CI](https://github.com/dereuromark/cakephp-workflow/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/dereuromark/cakephp-workflow/actions/workflows/ci.yml?query=branch%3Amaster)
[![codecov](https://img.shields.io/codecov/c/github/dereuromark/cakephp-workflow/master.svg)](https://codecov.io/gh/dereuromark/cakephp-workflow)
[![Latest Stable Version](https://poser.pugx.org/dereuromark/cakephp-workflow/v/stable.svg)](https://packagist.org/packages/dereuromark/cakephp-workflow)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.2-8892BF.svg)](https://php.net/)
[![License](https://poser.pugx.org/dereuromark/cakephp-workflow/license.svg)](LICENSE)
[![Coding Standards](https://img.shields.io/badge/cs-PhpCollective-purple.svg?style=flat-square)](https://github.com/php-collective/code-sniffer)

This branch is for **CakePHP 5.2+**. See [version map](https://github.com/dereuromark/cakephp-workflow/wiki#cakephp-version-map) for details.

State machine and workflow engine for CakePHP with PHP 8 Attributes, YAML/NEON config support, and admin UI.

> [!TIP]
> Try the live demo: <https://sandbox.dereuromark.de/workflow-sandbox>

## Requirements

- CakePHP 5.2+

## Installation

```bash
composer require dereuromark/cakephp-workflow
```

Load the plugin:

```bash
bin/cake plugin load Workflow
```

Run migrations:

```bash
bin/cake migrations migrate --plugin Workflow
```

The generic `entity_id` column defaults to `biginteger` (integer primary keys). If your
workflow-enabled tables use UUID/char primary keys, widen it in your own migration — see
[Installation: Entity id type](https://dereuromark.github.io/cakephp-workflow/guide/installation#entity-id-type-uuid-primary-keys).

## Configuration

Configure the plugin in your `config/app.php`:

```php
'Workflow' => [
    'adminAccess' => function (\Cake\Http\ServerRequest $request): bool {
        $identity = $request->getAttribute('identity');

        return $identity !== null && in_array('admin', (array)$identity->roles, true);
    },
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
    'adminBackUrl' => ['plugin' => false, 'controller' => 'Dashboard', 'action' => 'index'],
    'adminActorResolver' => function (string $userId, ?\Workflow\Model\Entity\WorkflowTransition $transition = null): string {
        return 'Admin #' . $userId;
    },
],
```

The admin UI is fail-closed by default: you must provide `Workflow.adminAccess`
to expose `/admin/workflow/...`. Manual admin actions also record actor and
context metadata; when you still rely on legacy session auth, the admin logger
falls back to `Auth.User.id` automatically.

## Defining Workflows

### Using PHP 8 Attributes (Recommended)

Create state classes in your namespace:

```php
<?php
namespace App\Workflow\Order;

use Workflow\Attribute\StateMachine;
use Workflow\State\AbstractState;

#[StateMachine(name: 'order', table: 'Orders', field: 'state')]
abstract class OrderState extends AbstractState
{
}
```

```php
<?php
namespace App\Workflow\Order;

use Workflow\Attribute\Command;
use Workflow\Attribute\FinalState;
use Workflow\Attribute\Guard;
use Workflow\Attribute\InitialState;
use Workflow\Attribute\Transition;

#[InitialState]
#[Transition(to: PaidState::class, name: 'pay', happy: true)]
class PendingState extends OrderState
{
    #[Guard('pay')]
    public function ensurePayable(): bool|string
    {
        return (float)$this->getEntity()?->get('total') > 0
            ? true
            : 'Order total must be positive';
    }

    #[Command('pay')]
    public function markPaymentCaptured(): void
    {
        $this->getEntity()?->set('payment_captured', true);
    }
}
```

```php
<?php
namespace App\Workflow\Order;

use Workflow\Attribute\FinalState;
use Workflow\Attribute\OnEnter;

#[FinalState]
class PaidState extends OrderState
{
    #[OnEnter]
    public function sendReceipt(): void
    {
        // Runs after the entity enters the paid state.
    }
}
```

### Using NEON or YAML

Install the optional parser you want:

- NEON: `composer require nette/neon`
- YAML: `composer require symfony/yaml`

Create workflow files in `config/workflows/`:

```neon [config/workflows/order.neon]
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

## Using the Workflow

Add the behavior to your table:

```php
public function initialize(array $config): void
{
    $this->addBehavior('Workflow.Workflow', [
        'workflow' => 'order',
    ]);
}
```

Apply transitions:

```php
$behavior = $this->Orders->getBehavior('Workflow');

if ($behavior->canTransition($order, 'pay')) {
    // Atomic: applies transition, saves entity, logs - all in one transaction
    $result = $behavior->transition($order, 'pay', ['user_id' => $userId]);
}
```

See the [documentation](https://dereuromark.github.io/cakephp-workflow/) for the full API.

## CLI Commands

```bash
bin/cake workflow init order Orders    # Scaffold new workflow
bin/cake workflow list                 # List all workflows
bin/cake workflow show order           # Show workflow details
bin/cake workflow validate             # Validate definitions
```

## Features

- PHP 8 Attributes or NEON/YAML definitions
- Guards, commands, and lifecycle callbacks
- Audit logging with user tracking
- Pessimistic locking for concurrent transitions
- Automatic timeouts
- Admin UI with Mermaid.js diagrams
- CLI tools for management and validation

## Documentation

Full documentation: https://dereuromark.github.io/cakephp-workflow/
