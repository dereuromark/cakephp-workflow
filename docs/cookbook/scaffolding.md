# Scaffolding

The plugin provides CLI commands to generate workflow code quickly.

## Create a New Workflow

Generate a complete workflow skeleton:

```bash
bin/cake workflow init order Orders
```

This creates three files in `src/Workflow/Order/`:

| File | Purpose |
|------|---------|
| `BaseOrderState.php` | Abstract base with `#[StateMachine]` attribute |
| `PendingState.php` | Initial state with `#[InitialState]` |
| `CompletedState.php` | Final state with `#[FinalState]` |

The workflow is immediately usable after creation.

## Add States

Add states to an existing workflow using Bake:

```bash
# Basic state
bin/cake bake workflow_state Order/Shipped

# Final state
bin/cake bake workflow_state Order/Delivered --final

# Failed state
bin/cake bake workflow_state Order/Cancelled --failed

# With transition
bin/cake bake workflow_state Order/Shipped \
    --transition-to Delivered \
    --transition-name deliver
```

::: tip
Requires `cakephp/bake` to be installed and the Bake plugin loaded.
:::

## Generated Code

### Base State

```php
namespace App\Workflow\Order;

use Workflow\Attribute\StateMachine;
use Workflow\State\AbstractState;

#[StateMachine(name: 'order', table: 'Orders', field: 'state')]
abstract class BaseOrderState extends AbstractState
{
}
```

### Initial State

```php
namespace App\Workflow\Order;

use Workflow\Attribute\InitialState;
use Workflow\Attribute\Transition;

#[InitialState]
#[Transition(to: CompletedState::class, name: 'complete', happy: true)]
class PendingState extends BaseOrderState
{
}
```

### Adding Guards and Commands

After generating, add guards and commands directly to the state class:

```php
#[InitialState]
#[Transition(to: PaidState::class, name: 'pay', happy: true)]
class PendingState extends BaseOrderState
{
    #[Guard('pay')]
    public function ensurePayable(): bool|string
    {
        return $this->getEntity()->total > 0
            ? true
            : 'Order total must be positive';
    }

    #[Command('pay')]
    public function capturePayment(): void
    {
        $this->getEntity()->set('paid_at', new DateTime());
    }
}
```

## Best Practices

1. **Use descriptive names** - State classes should clearly indicate their purpose
2. **Group by workflow** - Keep related states in the same namespace
3. **Add guards early** - Scaffold provides structure, you add the logic
4. **Mark happy path** - Use `happy: true` on primary transitions
