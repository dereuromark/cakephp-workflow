# Attributes

Attributes are the recommended way to define workflows.

## Base State Class

```php [src/Workflow/Order/BaseOrderState.php]
namespace App\Workflow\Order;

use Workflow\Attribute\StateMachine;
use Workflow\State\AbstractState;

#[StateMachine(name: 'order', table: 'Orders', field: 'state')]
abstract class BaseOrderState extends AbstractState
{
}
```

This marks the workflow root and defines:

- workflow name
- table alias
- state field

## Initial State

```php [src/Workflow/Order/State/PendingState.php]
use Workflow\Attribute\Command;
use Workflow\Attribute\Guard;
use Workflow\Attribute\InitialState;
use Workflow\Attribute\Transition;

#[InitialState]
#[Transition(to: PaidState::class, name: 'pay', happy: true)]
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

## Final State

```php [src/Workflow/Order/State/PaidState.php]
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

Guards validate whether a transition is allowed. They return `true` to allow, or a string message to block:

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

Multiple guards for the same transition are evaluated in sequence. If any guard returns a string, the transition is blocked.

## Commands

Commands execute side effects when a transition succeeds:

```php
#[Command('pay')]
public function markPaymentCaptured(): void
{
    $this->getEntity()?->set('payment_captured', true);
    $this->getEntity()?->set('paid_at', new DateTime());
}
```

Commands run after guards pass but before the entity is saved. They can modify the entity or trigger external actions.

## Lifecycle Callbacks

`OnEnter` and `OnExit` run when entering or leaving a state:

```php
#[OnEnter]
public function sendWelcomeEmail(): void
{
    // Runs when entity enters this state
}

#[OnExit]
public function logStateExit(): void
{
    // Runs when entity leaves this state
}
```

## Common Attributes

State-level:

- `StateMachine` - marks the base class with workflow metadata
- `InitialState` - marks the starting state for new entities
- `FinalState` - marks terminal states (no outgoing transitions allowed)
- `FailedState` - marks error/failure states (implies final)
- `Label` - human-readable label for the state
- `Color` - hex color for visualization (e.g., `#00AA00`)
- `Flag` - custom metadata tags (e.g., `done`, `billable`)
- `RequireReason` - require a reason when entering this state

Method-level:

- `Guard` - conditional check for transitions
- `Command` - action to run on transition
- `OnEnter` - callback when entering the state
- `OnExit` - callback when leaving the state
- `Timeout` - automatic transition after duration

Timeout durations support ISO-8601 intervals like `PT30M` and relative strings like `2 hours`.

## Why the Current Model Uses State Methods

The current API keeps guards and commands close to the state that owns them.

That gives:

- strong locality
- low registration overhead
- clearer workflow reading for most app teams

It is especially effective when the logic is specific to one workflow.
