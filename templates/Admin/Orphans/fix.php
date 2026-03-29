<?php
/**
 * @var \Cake\View\View $this
 * @var string $workflow
 * @var \Workflow\Engine\Definition\Definition $definition
 * @var \Cake\Datasource\EntityInterface $entity
 * @var string $entityId
 * @var string|null $currentState
 * @var array<string, string> $validStates
 * @var string $field
 */
$this->assign('title', 'Fix Orphaned Entity');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-wrench me-2"></i>Fix Orphaned Entity
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item"><?= $this->Html->link('Orphans', ['action' => 'index']) ?></li>
                <li class="breadcrumb-item active">Fix #<?= h($entityId) ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Entity Details</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Workflow</dt>
                    <dd class="col-sm-8">
                        <?= $this->Html->link(
                            h($workflow),
                            ['controller' => 'Workflows', 'action' => 'view', $workflow],
                        ) ?>
                    </dd>

                    <dt class="col-sm-4">Table</dt>
                    <dd class="col-sm-8"><code><?= h($definition->getTable()) ?></code></dd>

                    <dt class="col-sm-4">Entity ID</dt>
                    <dd class="col-sm-8">#<?= h($entityId) ?></dd>

                    <dt class="col-sm-4">State Field</dt>
                    <dd class="col-sm-8"><code><?= h($field) ?></code></dd>

                    <dt class="col-sm-4">Current State</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-danger"><?= h($currentState ?? 'NULL') ?></span>
                        <small class="text-muted">(invalid)</small>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Set New State</div>
            <div class="card-body">
                <?= $this->Form->create(null, ['url' => ['action' => 'fix', $workflow, $entityId]]) ?>

                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This will directly set the entity's state, bypassing all workflow guards and validations.
                    Use this only to recover orphaned entities.
                </div>

                <?= $this->Form->control('new_state', [
                    'type' => 'select',
                    'options' => $validStates,
                    'empty' => '-- Select State --',
                    'label' => 'New State',
                    'class' => 'form-select',
                    'required' => true,
                ]) ?>

                <?= $this->Form->control('reason', [
                    'type' => 'textarea',
                    'label' => 'Reason for Fix',
                    'placeholder' => 'Explain why this entity is being fixed...',
                    'class' => 'form-control',
                    'rows' => 3,
                ]) ?>

                <div class="mt-3">
                    <?= $this->Form->button('Fix Entity', [
                        'class' => 'btn btn-warning',
                        'type' => 'submit',
                    ]) ?>
                    <?= $this->Html->link('Cancel', ['action' => 'index', '?' => ['workflow' => $workflow]], [
                        'class' => 'btn btn-outline-secondary',
                    ]) ?>
                </div>

                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">Valid States in "<?= h($workflow) ?>" Workflow</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>State</th>
                    <th>Label</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($definition->getStates() as $state) { ?>
                    <tr>
                        <td><code><?= h($state->getName()) ?></code></td>
                        <td><?= h($state->getLabel() ?: '-') ?></td>
                        <td>
                            <?php if ($state->isInitial()) { ?>
                                <span class="badge bg-info">Initial</span>
                            <?php } ?>
                            <?php if ($state->isFinal()) { ?>
                                <span class="badge bg-success">Final</span>
                            <?php } ?>
                            <?php if ($state->isFailed()) { ?>
                                <span class="badge bg-danger">Failed</span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
