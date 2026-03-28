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
        'configPath' => CONFIG . 'workflows',
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

use Workflow\Attribute\InitialState;
use Workflow\Attribute\StateMachine;
use Workflow\State\AbstractState;

#[StateMachine(table: 'Orders', field: 'state')]
#[InitialState]
class PendingState extends AbstractState
{
}
```

```php
<?php
namespace App\Workflow\Order;

use Workflow\Attribute\FinalState;
use Workflow\State\AbstractState;

#[FinalState]
class CompletedState extends AbstractState
{
}
```

```php
<?php
namespace App\Workflow\Order;

use Workflow\Attribute\Transition;
use Workflow\State\AbstractState;

#[Transition(from: [PendingState::class], to: PaidState::class, happy: true)]
class PayState extends AbstractState
{
    public function guard(): bool
    {
        // Return true if transition is allowed
        return $this->entity->total > 0;
    }

    public function onEnter(): void
    {
        // Execute when entering this state
    }
}
```

### Using YAML

Install symfony/yaml: `composer require symfony/yaml`

Create workflow files in `config/workflows/`:

```yaml
# config/workflows/order.yaml
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

### Using NEON

Install nette/neon: `composer require nette/neon`

Create workflow files in `config/workflows/`:

```neon
# config/workflows/order.neon
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

## Admin Dashboard

Access the admin dashboard at `/workflow/admin/workflows`.

## CLI Commands

```bash
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
- **YAML/NEON Support**: Alternative configuration via YAML or NEON files
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

