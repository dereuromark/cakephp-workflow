<?php
/**
 * @var \Cake\View\View $this
 * @var iterable<\Workflow\Model\Entity\WorkflowLock> $locks
 * @var string|null $workflow
 * @var string $status
 * @var array<string> $workflowNames
 */

use Cake\I18n\DateTime;

$this->assign('title', 'Locks');
$now = DateTime::now();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-lock me-2"></i>Locks
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item active">Locks</li>
            </ol>
        </nav>
    </div>
    <div>
        <?= $this->Form->postLink(
            '<i class="bi bi-trash"></i> Cleanup Expired',
            ['action' => 'cleanup'],
            [
                'class' => 'btn btn-outline-warning',
                'confirm' => 'Remove all expired locks?',
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
                        'active' => 'Active',
                        'expired' => 'Expired',
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
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Workflow</th>
                    <th>Entity</th>
                    <th>Locked By</th>
                    <th>Created</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($locks as $lock) { ?>
                    <?php
                    $isExpired = $lock->expires_at <= $now;
                    ?>
                    <tr class="<?= $isExpired ? 'table-secondary' : '' ?>">
                        <td><?= h($lock->id) ?></td>
                        <td>
                            <?= $this->Html->link(
                                h($lock->workflow_name),
                                ['controller' => 'Workflows', 'action' => 'view', $lock->workflow_name],
                            ) ?>
                        </td>
                        <td>#<?= h($lock->foreign_key) ?></td>
                        <td><code><?= h($lock->locked_by ?? '-') ?></code></td>
                        <td>
                            <span title="<?= h($lock->created) ?>">
                                <?= $lock->created->diffForHumans() ?>
                            </span>
                        </td>
                        <td>
                            <span title="<?= h($lock->expires_at) ?>">
                                <?= $lock->expires_at->diffForHumans() ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($isExpired) { ?>
                                <span class="badge bg-secondary">Expired</span>
                            <?php } else { ?>
                                <span class="badge bg-success">Active</span>
                            <?php } ?>
                        </td>
                        <td>
                            <?= $this->Form->postLink(
                                '<i class="bi bi-unlock"></i> Release',
                                ['action' => 'release', $lock->id],
                                [
                                    'class' => 'btn btn-sm btn-outline-danger',
                                    'confirm' => 'Release this lock?',
                                    'escapeTitle' => false,
                                    'block' => true,
                                ],
                            ) ?>
                        </td>
                    </tr>
                <?php } ?>
                <?php if (!$locks->count()) { ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No locks found.</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <?= $this->Paginator->counter('Showing {{start}} to {{end}} of {{count}} locks') ?>
        <div class="pagination justify-content-center mb-0 mt-3">
            <?= $this->Paginator->numbers() ?>
        </div>
    </div>
</div>
