# Comparison and Gaps

This plugin is already stronger than older CakePHP workflow packages in several areas:

- attribute-first definitions
- Cake ORM behavior integration
- Mermaid diagrams without GraphViz
- built-in workflow analyzer and admin UI

## Compared with `cakephp-statemachine`

This package has the cleaner modern developer experience.

The older package still has some ideas worth studying:

- versioned process files

## Compared with Symfony Workflow

Symfony remains ahead in:

- multi-place workflow support
- marking-store abstraction
- metadata system
- broader event semantics

This plugin remains easier to adopt inside a CakePHP app.

## Features and Gaps

### Single-State (State Machine) Model

> This is a design choice, not a gap.

This plugin is a **state machine** - an entity is in exactly ONE state at a time.

Symfony also supports **workflow mode** where an entity can be in multiple "places" simultaneously (Petri net style). This is called "marking":

```
State Machine (this plugin):      Workflow with Marking:

    [draft]                           [draft]
       ↓                                 ↓
    [review]  ← entity is HERE        [tech_review]   ← token here
       ↓                              [legal_review]  ← AND token here
    [published]                       [finance_review]← AND token here
                                         ↓
                                      [published]
```

| Aspect | State Machine | Workflow (Marking) |
|--------|---------------|-------------------|
| Storage | `state = "review"` | `places = {"tech_review": 1, "legal_review": 1}` |
| Simultaneous states | No | Yes |
| Use case | Linear flows, simple branching | Parallel approvals, fork/join |

**Why this plugin uses state machine only:**

For 95%+ of CakePHP applications, single-state is sufficient. Parallel approvals can be modeled with boolean fields (`tech_approved`, `legal_approved`) or separate entities. The state machine model is simpler to understand, debug, and maintain.

### No General Metadata System

Symfony allows attaching arbitrary metadata to workflows, places, and transitions:

```yaml
# Symfony approach
transitions:
  publish:
    from: review
    to: published
    metadata:
      priority: high
      sla_hours: 24
      required_role: editor
      ui_color: green
```

This plugin has specific fields (`label`, `color`, `flags`) but no open-ended metadata bag. Teams needing custom data (SLA values, role requirements, UI hints, documentation) must extend the definition classes or store metadata elsewhere.

**Workaround:** Use state/transition `flags` for simple categorical metadata, or maintain a separate configuration file for extended attributes.

### Batch Orchestration

The `WorkflowBatchService` provides bulk transition operations:

```php
use Workflow\Service\WorkflowBatchService;

$batchService = new WorkflowBatchService();

// Transition all entities in a specific state
$result = $batchService->applyToState($ordersTable, 'pending', 'expire');

// Transition entities from a custom query
$query = $ordersTable->find()
    ->where(['state' => 'pending', 'created <' => $sevenDaysAgo]);
$result = $batchService->applyToQuery($ordersTable, $query, 'expire');

// Transition a list of entities
$result = $batchService->applyToEntities($ordersTable, $entities, 'approve');

// Check results
echo "Processed: {$result->getSuccessCount()}/{$result->getTotal()}";
if ($result->hasFailures()) {
    foreach ($result->getFailures() as $failure) {
        // Handle failed transitions
    }
}
```

Options:
- `$limit` - Maximum entities to process
- `$stopOnFailure` - Stop processing after first failure
- `$context` - Context passed to each transition

### Versioning Status

Supported:
- Version number on definitions (stored and logged)
- Version displayed in admin UI and tracked in transition history
- Graceful degradation: orphaned states (left behind by a definition change) no
  longer crash reads/display — see [Versioning & drift safety](../guide/versioning.md)
- Opt-in per-record version stamp (`versioning` behavior config) to detect drift
- Migration tooling: `workflow stamp` (backfill) and `workflow migrate`
  (re-stamp stale records, map orphaned records forward with `--map`)

Not yet implemented:
- Running multiple versions concurrently (old records keep executing the old
  definition while new records use the new one)
- Version comparison/diff tools
