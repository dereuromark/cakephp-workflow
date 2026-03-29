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

## ORM Behavior API

The `Workflow.Workflow` behavior adds methods to your Table class.

### Configuration

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

## Next Steps

- [Definitions](/definitions/) - Define workflows with attributes or config files
- [Persisted Transitions](/integration/persisted-transitions) - High-level `transition()` API
- [View Helper](/integration/view-helper) - Render diagrams and buttons in templates
