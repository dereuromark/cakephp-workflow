# Behavior Integration

The plugin provides two ways to interact with workflows:

1. **Workflow Object** (recommended) - clean, entity-centric API via `WorkflowRegistry`
2. **ORM Behavior** - table-level methods mixed into your Table class

## Workflow Object API

The `Workflow` class provides a Symfony-style API for working with entities:

```php
// In your controller, inject or fetch the registry
$registry = $this->getService(WorkflowRegistry::class);

// Get a workflow for an entity (auto-detects workflow from table)
$workflow = $registry->get($order);

// Or specify the workflow name explicitly
$workflow = $registry->get($order, 'order');

// Check if transition is allowed
if ($workflow->can('pay')) {
    $result = $workflow->apply('pay', ['user_id' => $userId]);
    if ($result->isSuccess()) {
        $this->Orders->saveOrFail($order);
    }
}

// Query state
$workflow->getStateName();           // 'pending'
$workflow->getState();               // State object
$workflow->isInState('pending');     // true
$workflow->isInFinalState();         // false
$workflow->hasFlag('done');          // false

// Get transitions
$workflow->getEnabledTransitions();  // ['pay', 'cancel'] - passes guards
$workflow->getAvailableTransitions(); // ['pay', 'cancel'] - ignores guards

// Access definition and entity
$workflow->getDefinition();          // Definition object
$workflow->getEntity();              // The entity
$workflow->getName();                // 'order'
```

::: tip Transaction Safety
The `apply()` method only changes the entity in memory. You must call `save()` to persist. For atomic operations where apply + save must succeed or fail together, use the behavior's `transition()` method instead. See [Persisted Transitions](/integration/persisted-transitions).
:::

## ORM Behavior API

The `Workflow.Workflow` behavior adds methods to your Table class.

### Configuration

```php [src/Model/Table/OrdersTable.php]
$this->addBehavior('Workflow.Workflow', [
    'workflow' => 'order',
    'validateOnSave' => true,
    'autoSave' => false,
    'autoLog' => false,
    'logAllOutcomes' => true,
    'entityTable' => 'Orders',
]);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `workflow` | string | `null` | Workflow name (auto-detected from definition if not set) |
| `validateOnSave` | bool | `true` | Prevent direct state changes on save |
| `autoSave` | bool | `false` | Auto-save entity after transition |
| `autoLog` | bool | `false` | Log transitions to `workflow_transitions` table |
| `logAllOutcomes` | bool | `true` | Log blocked/locked/error transitions for audit |
| `entityTable` | string | `null` | Entity table name (defaults to table name) |
| `useLocking` | bool | `false` | Use pessimistic locking (lock table) for transitions |
| `useOptimisticLock` | bool | `false` | Lock-free concurrency via compare-and-set on the state field (takes precedence over `useLocking`) |

## Concurrency: Pessimistic vs Optimistic

Two ways to keep concurrent transitions from racing on the same record:

- **Pessimistic (`useLocking`)** — acquires a row in the `workflow_locks` table for the
  duration of the transition. A competing caller is told the record is `locked`.
- **Optimistic (`useOptimisticLock`)** — no lock table. The state change is persisted
  with a compare-and-set: `UPDATE ... SET state = :to WHERE id = :id AND state = :from`.
  If another writer already advanced the row, the `UPDATE` matches nothing and the caller
  gets a `locked` (conflict) result instead of double-applying.

```php
$this->addBehavior('Workflow.Workflow', [
    'workflow' => 'order',
    'useOptimisticLock' => true,
]);
```

```php
$result = $this->Orders->getBehavior('Workflow')->transition($order, 'pay');
if ($result->isLocked()) {
    // Lost the race - reload and retry, or report a conflict.
}
```

Optimistic locking needs no extra table or `version` column (it compares on the state
field) and suits high-contention or multi-server setups; it takes precedence over
`useLocking` when both are set. Wrap retries around the conflict result as needed.

## Audit Logging

When `autoLog` is enabled, all transition attempts are logged to the `workflow_transitions` table. The `logAllOutcomes` option (default: `true`) controls whether failed attempts are logged:

- **success**: Transition completed successfully
- **blocked**: Guards prevented the transition
- **locked**: Another process held the lock
- **error**: Command threw an exception

This provides a complete audit trail for compliance and debugging:

```php
// Get full transition history
$transitions = $this->Orders->getBehavior('Workflow')
    ->getTransitionHistory($order);

// Filter by status
$failedTransitions = $this->fetchTable('Workflow.WorkflowTransitions')
    ->find('failed')
    ->where(['entity_id' => $order->id])
    ->toArray();

// Check blocked reasons
foreach ($transitions as $t) {
    if ($t->isBlocked()) {
        $reasons = $t->getBlockedBy();
        // ['checkBalance' => 'Insufficient funds']
    }
}
```

Each transition record includes:
- `status`: success, blocked, locked, or error
- `from_state`, `to_state`: State before and after
- `user_id`, `reason`: Context from the transition call
- `context`: JSON with runtime metadata, blocked reasons, or error details

## Core Methods

Access behavior methods via `getBehavior()`:

```php
$behavior = $this->Orders->getBehavior('Workflow');
```

### Check if a transition is possible

```php
$behavior->canTransition($order, 'pay');
```

### Apply a transition

```php
$result = $behavior->applyTransition($order, 'pay', [
    'user_id' => '42',
    'reason' => 'Payment captured',
]);
```

### Run a persisted/orchestrated transition

```php
$result = $behavior->transition($order, 'pay', [
    'user_id' => '42',
    'reason' => 'Payment captured',
]);
```

For the full persisted API, per-call options, and default orchestration config,
see [Persisted Transitions](/integration/persisted-transitions).

For automatic timeout scheduling/cancellation during persisted transitions,
see [Timeout Orchestration](/integration/timeout-orchestration).

### Get available transitions

```php
$transitions = $behavior->getAvailableTransitions($order);
```

### Read the current state

```php
$state = $behavior->getCurrentState($order);
```

## Querying Entities by State

The behavior provides custom finders for querying entities based on workflow state properties.

### Find by flag

Query entities in states that have (or don't have) a specific flag:

```php
// Find all orders in states with the 'done' flag
$doneOrders = $this->Orders->find('withFlag', flag: 'done')->toArray();

// Find all orders NOT in states with the 'done' flag
$activeOrders = $this->Orders->find('withoutFlag', flag: 'done')->toArray();
```

### Find by final state

Query entities based on whether they're in a final state:

```php
// Find all completed orders (in any final state)
$completedOrders = $this->Orders->find('inFinalState')->toArray();

// Find all active orders (not in a final state)
$activeOrders = $this->Orders->find('notInFinalState')->toArray();
```

### Find by specific state

Query entities in a specific state:

```php
// Find all pending orders
$pendingOrders = $this->Orders->find('inState', state: 'pending')->toArray();
```

### Helper methods

Get state names programmatically for custom queries:

```php
$behavior = $this->Orders->getBehavior('Workflow');

// Get all state names with a specific flag
$doneStates = $behavior->getStateNamesWithFlag('done');
// Returns: ['completed', 'delivered']

// Get all state names without a specific flag
$notDoneStates = $behavior->getStateNamesWithoutFlag('done');
// Returns: ['pending', 'processing', 'shipped']

// Get all final state names
$finalStates = $behavior->getFinalStateNames();
// Returns: ['completed', 'cancelled']
```

## Save Protection

When `validateOnSave` is enabled, direct state mutation is rejected.

This prevents code from bypassing the workflow engine by changing the state field manually and calling `save()`.

For new entities:

- empty state is allowed
- the initial state is allowed
- any other state is rejected

For existing entities:

- state changes must go through `applyTransition()`

## Convenience Traits

Two optional traits give you statically-typed, discoverable access without reaching
through `getBehavior('Workflow')`.

Add `WorkflowTableTrait` to a table that has the behavior:

```php
use Cake\ORM\Table;
use Workflow\Model\Table\WorkflowTableTrait;

class OrdersTable extends Table
{
    use WorkflowTableTrait;

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->addBehavior('Workflow.Workflow', ['workflow' => 'order']);
    }
}
```

```php
$this->Orders->transition($order, 'pay', ['user_id' => $userId]); // save + log (+ lock when enabled)
$this->Orders->canTransition($order, 'pay');
$this->Orders->availableTransitions($order);
$this->Orders->currentState($order);
```

Add `WorkflowTrait` to the entity for read-only inspection helpers (mutating
transitions stay on the table):

```php
use Cake\ORM\Entity;
use Workflow\Model\Entity\WorkflowTrait;

class Order extends Entity
{
    use WorkflowTrait;
}
```

```php
$order->currentState();              // 'pending'
$order->canTransition('pay');        // true
$order->availableTransitions();      // ['pay']
$order->isInState('pending');        // true
$order->isFinalState();              // false
$order->hasStateFlag('done');        // false
```

## Next Steps

- [Definitions](/definitions/) - Define workflows with attributes or config files
- [Persisted Transitions](/integration/persisted-transitions) - High-level `transition()` API
- [View Helper](/integration/view-helper) - Render diagrams and buttons in templates
