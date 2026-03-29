<?php
/**
 * @var \Cake\View\View $this
 * @var iterable<\Workflow\Model\Entity\WorkflowTransition> $transitions
 * @var string|null $workflow
 * @var string|null $entityId
 * @var array<string> $workflowNames
 */
$this->assign('title', 'Transitions');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-arrow-left-right me-2"></i>Transitions
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item active">Transitions</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'row g-3']) ?>
            <div class="col-md-4">
                <?= $this->Form->control('workflow', [
                    'type' => 'select',
                    'options' => array_combine($workflowNames, $workflowNames),
                    'empty' => 'All Workflows',
                    'value' => $workflow,
                    'label' => false,
                    'class' => 'form-select',
                ]) ?>
            </div>
            <div class="col-md-4">
                <?= $this->Form->control('entity_id', [
                    'type' => 'text',
                    'placeholder' => 'Entity ID',
                    'value' => $entityId,
                    'label' => false,
                    'class' => 'form-control',
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
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Workflow</th>
                    <th>Entity</th>
                    <th>Transition</th>
                    <th>From</th>
                    <th></th>
                    <th>To</th>
                    <th>Actor</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transitions as $t) { ?>
                    <tr>
                        <td><?= h($t->id) ?></td>
                        <td>
                            <?= $this->Html->link(
                                h($t->workflow_name),
                                ['controller' => 'Workflows', 'action' => 'view', $t->workflow_name],
                            ) ?>
                        </td>
                        <td>#<?= h($t->entity_id) ?></td>
                        <td><code><?= h($t->transition_name) ?></code></td>
                        <td><span class="badge bg-secondary"><?= h($t->from_state) ?></span></td>
                        <td><i class="bi bi-arrow-right text-muted"></i></td>
                        <td><span class="badge bg-primary"><?= h($t->to_state) ?></span></td>
                        <td><?= h($t->user_id ?? '-') ?></td>
                        <td>
                            <span title="<?= h($t->created) ?>"><?= $t->created->diffForHumans() ?></span>
                        </td>
                    </tr>
                <?php } ?>
                <?php if (!$transitions->count()) { ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No transitions found.</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <?= $this->Paginator->counter('Showing {{start}} to {{end}} of {{count}} transitions') ?>
        <div class="pagination justify-content-center mb-0 mt-3">
            <?= $this->Paginator->numbers() ?>
        </div>
    </div>
</div>
