<?php
/**
 * @var \Cake\View\View $this
 * @var array<array{workflow: string, table: string, field: string, entity: \Cake\Datasource\EntityInterface, current_state: string, valid_states: array<string>}> $orphans
 * @var array<string, int> $orphanCounts
 * @var int $totalOrphans
 * @var array<string> $workflowNames
 * @var string|null $selectedWorkflow
 */
$this->assign('title', 'Orphaned Items');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-exclamation-triangle me-2"></i>Orphaned Items
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item active">Orphans</li>
            </ol>
        </nav>
    </div>
</div>

<?php if ($totalOrphans > 0) { ?>
    <div class="alert alert-warning mb-4">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Found <strong><?= $totalOrphans ?></strong> orphaned item(s) with invalid states.
        These items have a state value that doesn't match any defined state in their workflow.
    </div>
<?php } ?>

<!-- Stats by Workflow -->
<div class="row g-3 mb-4">
    <?php foreach ($orphanCounts as $workflowName => $count) { ?>
        <div class="col-md-3">
            <div class="card <?= $count > 0 ? 'border-danger' : '' ?>">
                <div class="card-body text-center">
                    <div class="h3 mb-0 <?= $count > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= $count ?>
                    </div>
                    <small class="text-muted"><?= h($workflowName) ?></small>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'row g-3']) ?>
            <div class="col-md-3">
                <?= $this->Form->control('workflow', [
                    'type' => 'select',
                    'options' => array_combine($workflowNames, $workflowNames),
                    'empty' => 'All Workflows',
                    'value' => $selectedWorkflow,
                    'label' => false,
                    'class' => 'form-select',
                ]) ?>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Filter
                </button>
                <?= $this->Html->link('Clear', ['action' => 'index'], ['class' => 'btn btn-outline-secondary']) ?>
            </div>
        <?= $this->Form->end() ?>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Orphaned Items</span>
        <span class="badge bg-danger"><?= $totalOrphans ?> orphans</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Workflow</th>
                    <th>Table</th>
                    <th>Entity ID</th>
                    <th>Current State</th>
                    <th>Valid States</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orphans as $orphan) { ?>
                    <tr class="table-danger">
                        <td>
                            <?= $this->Html->link(
                                h($orphan['workflow']),
                                ['controller' => 'Workflows', 'action' => 'view', $orphan['workflow']],
                            ) ?>
                        </td>
                        <td><code><?= h($orphan['table']) ?></code></td>
                        <td>#<?= h($orphan['entity']->get('id')) ?></td>
                        <td>
                            <span class="badge bg-danger">
                                <?= h($orphan['current_state'] ?? 'NULL') ?>
                            </span>
                        </td>
                        <td>
                            <?php foreach ($orphan['valid_states'] as $validState) { ?>
                                <span class="badge bg-secondary"><?= h($validState) ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                <?php if (empty($orphans)) { ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            No orphaned items found. All items have valid states.
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($orphans)) { ?>
    <div class="card mt-4">
        <div class="card-header">How to Fix Orphans</div>
        <div class="card-body">
            <p>Orphaned items have a state value that doesn't exist in the workflow definition. This can happen when:</p>
            <ul class="mb-3">
                <li>A state was removed from the workflow definition</li>
                <li>A state name was changed</li>
                <li>Data was manually modified in the database</li>
                <li>A migration didn't update existing records</li>
            </ul>
            <p class="mb-0">To fix orphans, either update the entity's state field to a valid state value, or add the missing state to the workflow definition.</p>
        </div>
    </div>
<?php } ?>
