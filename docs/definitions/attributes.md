# Attributes

Attributes are the recommended way to define workflows. See the [Overview](./index.md) for conceptual background on states, transitions, guards, and commands.

## Base State Class

Every workflow starts with an abstract base class marked with `#[StateMachine]`:

```php [src/Workflow/Order/BaseOrderState.php]
namespace App\Workflow\Order;

use Workflow\Attribute\StateMachine;
use Workflow\State\AbstractState;

#[StateMachine(name: 'order', table: 'Orders', field: 'state')]
abstract class BaseOrderState extends AbstractState
{
}
```

## State Classes

Each state is a concrete class extending your base. Use attributes to mark state types and define transitions:

```php [src/Workflow/Order/PendingState.php]
namespace App\Workflow\Order;

use Workflow\Attribute\Command;
use Workflow\Attribute\Guard;
use Workflow\Attribute\InitialState;
use Workflow\Attribute\Transition;

#[InitialState]
#[Transition(to: PaidState::class, name: 'pay', happy: true)]
#[Transition(to: CancelledState::class, name: 'cancel')]
class PendingState extends BaseOrderState
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

```php [src/Workflow/Order/PaidState.php]
namespace App\Workflow\Order;

use Workflow\Attribute\FinalState;
use Workflow\Attribute\OnEnter;

#[FinalState]
class PaidState extends BaseOrderState
{
    #[OnEnter]
    public function sendReceipt(): void
    {
        // Runs after the entity enters the paid state
    }
}
```

## Guards

Guard methods validate transitions. Return `true` to allow, or a message string to block:

```php
#[Guard('pay')]
public function ensurePayable(): bool|string
{
    return (float)$this->getEntity()?->get('total') > 0
        ? true
        : 'Order total must be positive';
}

#[Guard('pay')]
public function ensureNotAlreadyPaid(): bool|string
{
    return !$this->getEntity()?->get('payment_captured')
        ? true
        : 'Order was already paid';
}
```

Multiple guards for the same transition run in sequence. Any blocking message stops the transition.

## Commands

Command methods run when a transition succeeds:

```php
#[Command('pay')]
public function markPaymentCaptured(): void
{
    $this->getEntity()?->set('payment_captured', true);
    $this->getEntity()?->set('paid_at', new DateTime());
}
```

## Lifecycle Callbacks

`#[OnEnter]` and `#[OnExit]` run when entering or leaving a state:

```php
#[OnEnter]
public function notifyCustomer(): void
{
    // Runs when entity enters this state
}

#[OnExit]
public function cleanupResources(): void
{
    // Runs when entity leaves this state
}
```

## Attribute Reference

### State-level

| Attribute | Description |
|-----------|-------------|
| `#[StateMachine(name, table, field)]` | Marks the base class with workflow metadata |
| `#[InitialState]` | Starting state for new entities |
| `#[FinalState]` | Terminal state (no outgoing transitions) |
| `#[FailedState]` | Error state (implies final) |
| `#[Label('Display Name')]` | Human-readable label |
| `#[Color('#00AA00')]` | Hex color for visualization |
| `#[Flag('done')]` | Custom metadata tag |
| `#[RequireReason]` | Require reason when entering |

### Method-level

| Attribute | Description |
|-----------|-------------|
| `#[Guard('transition')]` | Conditional check (return `bool\|string`) |
| `#[Command('transition')]` | Action on successful transition |
| `#[OnEnter]` | Callback when entering this state |
| `#[OnExit]` | Callback when leaving this state |
| `#[Timeout('1 hour', 'expire')]` | Auto-transition after duration |

### Transition-level

```php
#[Transition(
    to: TargetState::class,  // Required: target state class
    name: 'approve',         // Required: transition identifier
    happy: true,             // Optional: mark as happy path
)]
```

## Why State Methods?

Guards and commands live in the state class that owns them. This provides:

- Strong locality - logic is close to the state
- IDE support - refactoring and navigation work naturally
- Clear ownership - each state manages its own transition logic
