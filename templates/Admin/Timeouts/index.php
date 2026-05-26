<?php
/**
 * @var \Cake\View\View $this
 * @var iterable<\Workflow\Model\Entity\WorkflowTimeout> $timeouts
 * @var string|null $workflow
 * @var string $status
 * @var array<string> $workflowNames
 */

use Cake\I18n\DateTime;

$this->assign('title', 'Timeouts');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-clock-history me-2"></i>Timeouts
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item active">Timeouts</li>
            </ol>
        </nav>
    </div>
    <div>
        <?= $this->Form->postLink(
            '<i class="bi bi-lightning-charge"></i> Execute Due Now',
            ['action' => 'executeDue'],
            [
                'class' => 'btn btn-outline-warning',
                'confirm' => 'Execute all due pending timeouts now?',
                'escapeTitle' => false,
                'block' => true,
            ],
        ) ?>
    </div>
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
                    'value' => $workflow,
                    'label' => false,
                    'class' => 'form-select',
                ]) ?>
            </div>
            <div class="col-md-3">
                <?= $this->Form->control('status', [
                    'type' => 'select',
                    'options' => [
                        'pending' => 'Pending',
                        'processed' => 'Processed',
                        'all' => 'All',
                    ],
                    'value' => $status,
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
    <?= $this->Form->create(null, ['url' => ['action' => 'bulkExecute']]) ?>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 1%">
                        <input type="checkbox" class="form-check-input" data-timeout-select-all>
                    </th>
                    <th>ID</th>
                    <th>Workflow</th>
                    <th>Entity</th>
                    <th>From State</th>
                    <th>Transition</th>
                    <th>Due At</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($timeouts as $timeout) { ?>
                    <?php
                    $now = DateTime::now();
                    $isOverdue = !$timeout->processed && $timeout->due_at < $now;
                    ?>
                    <tr class="<?= $isOverdue ? 'table-warning' : '' ?>">
                        <td>
                            <?php if (!$timeout->processed) { ?>
                                <?= $this->Form->checkbox('timeout_ids[]', [
                                    'value' => $timeout->id,
                                    'class' => 'form-check-input',
                                ]) ?>
                            <?php } ?>
                        </td>
                        <td><?= h($timeout->id) ?></td>
                        <td>
                            <?= $this->Html->link(
                                h($timeout->workflow_name),
                                ['controller' => 'Workflows', 'action' => 'view', $timeout->workflow_name],
                            ) ?>
                        </td>
                        <td>#<?= h($timeout->foreign_key) ?></td>
                        <td><span class="badge bg-secondary"><?= h($timeout->current_state) ?></span></td>
                        <td><code><?= h($timeout->transition_name) ?></code></td>
                        <td>
                            <?php if ($isOverdue) { ?>
                                <span class="text-danger">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <?= $timeout->due_at->diffForHumans() ?>
                                </span>
                            <?php } else { ?>
                                <span title="<?= h($timeout->due_at) ?>">
                                    <?= $timeout->due_at->diffForHumans() ?>
                                </span>
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($timeout->processed) { ?>
                                <span class="badge bg-secondary">Processed</span>
                            <?php } else { ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php } ?>
                        </td>
                        <td>
                            <?= $this->Html->link(
                                '<i class="bi bi-eye"></i>',
                                ['action' => 'view', $timeout->id],
                                ['class' => 'btn btn-sm btn-outline-info', 'escapeTitle' => false, 'title' => 'View Details'],
                            ) ?>
                            <?php if (!$timeout->processed) { ?>
                                <?= $this->Form->postLink(
                                    '<i class="bi bi-play-fill"></i>',
                                    ['action' => 'execute', $timeout->id],
                                    [
                                        'class' => 'btn btn-sm btn-success',
                                        'confirm' => 'Execute this timeout now?',
                                        'escapeTitle' => false,
                                        'block' => true,
                                        'title' => 'Execute Now',
                                    ],
                                ) ?>
                                <?= $this->Form->postLink(
                                    '<i class="bi bi-x-circle"></i>',
                                    ['action' => 'cancel', $timeout->id],
                                    [
                                        'class' => 'btn btn-sm btn-outline-danger',
                                        'confirm' => 'Cancel this timeout?',
                                        'escapeTitle' => false,
                                        'block' => true,
                                        'title' => 'Cancel',
                                    ],
                                ) ?>
                            <?php } else { ?>
                                <?= $this->Form->postLink(
                                    '<i class="bi bi-arrow-repeat"></i>',
                                    ['action' => 'retry', $timeout->id],
                                    [
                                        'class' => 'btn btn-sm btn-outline-warning',
                                        'confirm' => 'Create a new timeout to retry this transition?',
                                        'escapeTitle' => false,
                                        'block' => true,
                                        'title' => 'Retry',
                                    ],
                                ) ?>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                <?php if (!$timeouts->count()) { ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No timeouts found.</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <?= $this->Paginator->counter('Showing {{start}} to {{end}} of {{count}} timeouts') ?>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="bi bi-play-fill"></i> Execute Selected
                </button>
                <?= $this->Form->button(
                    '<i class="bi bi-x-circle"></i> Cancel Selected',
                    [
                        'type' => 'submit',
                        'formaction' => $this->Url->build(['action' => 'bulkCancel']),
                        'formmethod' => 'post',
                        'class' => 'btn btn-sm btn-outline-danger',
                        'escapeTitle' => false,
                    ],
                ) ?>
            </div>
        </div>
        <div class="pagination justify-content-center mb-0 mt-3">
            <?= $this->Paginator->numbers() ?>
        </div>
    </div>
    <?= $this->Form->end() ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.querySelector('[data-timeout-select-all]');
    if (!selectAll) {
        return;
    }

    selectAll.addEventListener('change', function () {
        document.querySelectorAll('input[name="timeout_ids[]"]').forEach(function (checkbox) {
            checkbox.checked = selectAll.checked;
        });
    });
});
</script>
