# Order Workflow

An order workflow is the canonical example for this plugin.

Typical states:

- `pending`
- `paid`
- `packed`
- `shipped`
- `completed`
- `cancelled`

Typical transitions:

- `pay`
- `pack`
- `ship`
- `complete`
- `cancel`

## Good Practices

- keep transition names action-oriented
- use `reason` for cancellation or exceptional flows
- mark terminal failure states explicitly
- store operator identity in transition context

## Example

```php
#[Transition(to: PaidState::class, name: 'pay', happy: true)]
#[Transition(to: CancelledState::class, name: 'cancel')]
class PendingState extends BaseOrderState
{
    #[Guard('pay')]
    public function ensureHasPositiveTotal(): bool|string
    {
        return (float)$this->getEntity()?->get('total') > 0
            ? true
            : 'Order total must be positive';
    }
}
```

