# Attributes

Attributes are the recommended way to define workflows.

## Base State Class

```php
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

```php
use Workflow\Attribute\InitialState;
use Workflow\Attribute\Transition;

#[InitialState]
#[Transition(to: PaidState::class, name: 'pay', happy: true)]
class PendingState extends BaseOrderState
{
}
```

## Final State

```php
use Workflow\Attribute\FinalState;

#[FinalState]
class PaidState extends BaseOrderState
{
}
```

## Common Attributes

State-level:

- `StateMachine`
- `InitialState`
- `FinalState`
- `FailedState`
- `Label`
- `Color`
- `Flag`
- `RequireReason`

Method-level:

- `Guard`
- `Command`
- `OnEnter`
- `OnExit`
- `Timeout`

## Why the Current Model Uses State Methods

The current API keeps guards and commands close to the state that owns them.

That gives:

- strong locality
- low registration overhead
- clearer workflow reading for most app teams

It is especially effective when the logic is specific to one workflow.

