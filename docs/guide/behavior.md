# Behavior Integration

The main application API is the `Workflow.Workflow` ORM behavior.

## Configuration

```php [src/Model/Table/OrdersTable.php]
$this->addBehavior('Workflow.Workflow', [
    'workflow' => 'order',
    'validateOnSave' => true,
    'autoSave' => false,
    'autoLog' => false,
    'entityTable' => 'Orders',
]);
```

## Core Methods

### Check if a transition is possible

```php
$this->Orders->canTransition($order, 'pay');
```

### Apply a transition

```php
$result = $this->Orders->applyTransition($order, 'pay', [
    'user_id' => '42',
    'reason' => 'Payment captured',
]);
```

### Run a persisted/orchestrated transition

```php
$result = $this->Orders->transition($order, 'pay', [
    'user_id' => '42',
    'reason' => 'Payment captured',
]);
```

For the full persisted API, per-call options, and default orchestration config,
see [Persisted Transitions](./persisted-transitions).

For automatic timeout scheduling/cancellation during persisted transitions,
see [Timeout Orchestration](./timeout-orchestration).

### Get available transitions

```php
$transitions = $this->Orders->getAvailableTransitions($order);
```

### Read the current state

```php
$state = $this->Orders->getCurrentState($order);
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
// Get all state names with a specific flag
$doneStates = $this->Orders->getStateNamesWithFlag('done');
// Returns: ['completed', 'delivered']

// Get all state names without a specific flag
$notDoneStates = $this->Orders->getStateNamesWithoutFlag('done');
// Returns: ['pending', 'processing', 'shipped']

// Get all final state names
$finalStates = $this->Orders->getFinalStateNames();
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
