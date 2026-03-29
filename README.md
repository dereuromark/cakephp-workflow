# CakePHP Workflow Plugin

State machine and workflow engine for CakePHP with PHP 8 Attributes, YAML/NEON config support, and admin UI.

## Requirements

- PHP 8.2+
- CakePHP 5.2+

## Installation

```bash
composer require dereuromark/cakephp-workflow
```

Load the plugin in your `src/Application.php`:

```php
public function bootstrap(): void
{
    parent::bootstrap();
    // Required if you want to use `bin/cake bake workflow_state`
    $this->addPlugin('Bake');
    $this->addPlugin('Workflow');
}
```

Run migrations:

```bash
bin/cake migrations migrate --plugin Workflow
```

## Configuration

Configure the plugin in your `config/app.php`:

```php
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

::: code-group

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

```yaml [config/workflows/order.yaml]
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

## Using the Workflow

### In Models

Add the behavior to your table:

```php
public function initialize(array $config): void
{
    $this->addBehavior('Workflow.Workflow', [
        'workflow' => 'order',
    ]);
}
```

Then use it:

```php
// Check if transition is allowed
$canPay = $ordersTable->canTransition($order, 'pay');

// Apply transition
$result = $ordersTable->applyTransition($order, 'pay', [
    'user_id' => $userId,
    'reason' => 'Payment received',
]);

if ($result->isSuccess()) {
    $ordersTable->save($order);
}

// Get available transitions
$transitions = $ordersTable->getAvailableTransitions($order);
```

For a full persisted transition, use the higher-level API:

```php
$result = $ordersTable->transition($order, 'pay', [
    'user_id' => $userId,
    'reason' => 'Payment received',
]);
```

`transition()` is the orchestration entry point. It can save the entity,
write transition history, acquire a workflow lock, and wrap the whole operation
in a transaction for that call.

You can override the orchestration options per call:

```php
$result = $ordersTable->transition($order, 'pay', [], [
    'save' => true,
    'log' => true,
    'lock' => true,
    'transaction' => true,
]);
```

Use `applyTransition()` when you explicitly want the lower-level in-memory
state change and you will coordinate persistence yourself.

### Behavior Options

The behavior supports both long-lived defaults and per-call orchestration:

```php
public function initialize(array $config): void
{
    $this->addBehavior('Workflow.Workflow', [
        'workflow' => 'order',
        'autoSave' => false,
        'autoLog' => false,
        'useLocking' => null,
        'useTransaction' => true,
    ]);
}
```

- `autoSave`: Save the entity after a successful `applyTransition()`
- `autoLog`: Persist a transition history record after a successful transition
- `useLocking`: `true` forces locks, `false` disables them, `null` auto-detects from the lock table
- `useTransaction`: Wrap transition, save, and logging in one DB transaction

## Admin Dashboard

Access the admin dashboard at `/workflow/admin/workflows`.

## CLI Commands

```bash
# Scaffold a new attribute-based workflow
bin/cake workflow init order Orders

# Add another state class to an existing workflow (requires cakephp/bake)
bin/cake bake workflow_state Order/Shipped --transition-to Delivered --transition-name deliver

# List all workflows
bin/cake workflow list

# Show workflow details
bin/cake workflow show order

# Output Mermaid diagram
bin/cake workflow show order --mermaid

# Validate workflow definitions
bin/cake workflow validate

# Process pending timeouts
bin/cake workflow timeouts
```

## View Helper

Use the helper in your templates:

```php
// Include Mermaid.js
<?= $this->Workflow->includeMermaid() ?>

// Render workflow diagram
<?= $this->Workflow->diagram($definition) ?>

// Render state badge
<?= $this->Workflow->stateBadge($definition, $entity->state) ?>

// Render transition buttons
<?= $this->Workflow->transitionButtons($entity, $availableTransitions) ?>
```

## Features

- **PHP 8 Attributes**: Define workflows declaratively using modern PHP
- **NEON/YAML Support**: Alternative configuration via NEON or YAML files
- **State Types**: Initial, final, and failed state types
- **Guards**: Conditional transitions with guard methods
- **Commands**: Execute actions on state transitions
- **Happy Path**: Visual emphasis on primary workflow paths
- **State Flags**: Custom metadata on states
- **Audit Trail**: Full transition logging with user tracking
- **Locking**: Prevent concurrent transitions
- **Timeouts**: Automatic time-based transitions
- **Admin UI**: Visual dashboard with Mermaid.js diagrams
- **CLI Tools**: Workflow management and validation commands
- **Validation**: Detect unreachable states and dead ends
