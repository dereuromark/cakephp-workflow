<?php
/**
 * @var \Cake\View\View $this
 * @var iterable<\Workflow\Model\Entity\WorkflowTransition> $transitions
 * @var string|null $workflow
 * @var string|null $foreignKey
 * @var string|null $status
 * @var string|null $userId
 * @var string|null $adminAction
 * @var string|null $createdFrom
 * @var string|null $createdTo
 * @var array<string> $workflowNames
 * @var array<string, string> $statusOptions
 * @var array<string, string> $adminActionOptions
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
                <?= $this->Form->control('foreign_key', [
                    'type' => 'text',
                    'placeholder' => 'Entity ID',
                    'value' => $foreignKey,
                    'label' => false,
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-3">
                <?= $this->Form->control('status', [
                    'type' => 'select',
                    'options' => $statusOptions,
                    'value' => $status,
                    'label' => false,
                    'class' => 'form-select',
                ]) ?>
            </div>
            <div class="col-md-3">
                <?= $this->Form->control('user_id', [
                    'type' => 'text',
                    'placeholder' => 'Actor ID',
                    'value' => $userId,
                    'label' => false,
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-3">
                <?= $this->Form->control('admin_action', [
                    'type' => 'select',
                    'options' => $adminActionOptions,
                    'value' => $adminAction,
                    'label' => false,
                    'class' => 'form-select',
                ]) ?>
            </div>
            <div class="col-md-3">
                <?= $this->Form->control('created_from', [
                    'type' => 'date',
                    'value' => $createdFrom,
                    'label' => false,
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-3">
                <?= $this->Form->control('created_to', [
                    'type' => 'date',
                    'value' => $createdTo,
                    'label' => false,
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-3">
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
                    <th>Runtime</th>
                    <th>Actor</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transitions as $t) { ?>
                    <?php
                    $guards = $t->getGuardsEvaluated();
                    $commands = $t->getCommandsExecuted();
                    $usedLock = $t->usedLock();
                    $actorLabel = $t->getActorLabel();
                    $actorUrl = $t->getActorUrl();
                    ?>
                    <tr>
                        <td><?= h($t->id) ?></td>
                        <td>
                            <?= $this->Html->link(
                                h($t->workflow_name),
                                ['controller' => 'Workflows', 'action' => 'view', $t->workflow_name],
                            ) ?>
                        </td>
                        <td>#<?= h($t->foreign_key) ?></td>
                        <td>
                            <div><code><?= h($t->transition_name) ?></code></div>
                            <?php if ($t->reason) { ?>
                                <div class="small text-muted"><?= h($t->reason) ?></div>
                            <?php } ?>
                        </td>
                        <td><span class="badge bg-secondary"><?= h($t->from_state) ?></span></td>
                        <td><i class="bi bi-arrow-right text-muted"></i></td>
                        <td>
                            <span class="badge bg-primary"><?= h($t->to_state) ?></span>
                            <?php if ($t->isAdminAction()) { ?>
                                <div class="small text-warning-emphasis">Admin action</div>
                            <?php } ?>
                        </td>
                        <td class="text-muted small">
                            <?php if ($guards) { ?>
                                <span title="Guards: <?= h(implode(', ', $guards)) ?>"><i class="bi bi-shield-check"></i> <?= count($guards) ?></span>
                            <?php } ?>
                            <?php if ($commands) { ?>
                                <span class="ms-1" title="Commands: <?= h(implode(', ', $commands)) ?>"><i class="bi bi-gear"></i> <?= count($commands) ?></span>
                            <?php } ?>
                            <?php if ($usedLock) { ?>
                                <span class="ms-1" title="Used lock"><i class="bi bi-lock"></i></span>
                            <?php } ?>
                            <?php if (!$guards && !$commands && !$usedLock) { ?>
                                -
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($actorLabel && $actorUrl !== null) { ?>
                                <?= $this->Html->link(h($actorLabel), $actorUrl) ?>
                            <?php } else { ?>
                                <?= h($actorLabel ?? '-') ?>
                            <?php } ?>
                            <?php if ($t->getClientIp()) { ?>
                                <div class="small text-muted"><?= h($t->getClientIp()) ?></div>
                            <?php } ?>
                        </td>
                        <td>
                            <span title="<?= h($t->created) ?>"><?= $t->created->diffForHumans() ?></span>
                            <div class="mt-1">
                                <?= $this->Html->link(
                                    'Details',
                                    ['action' => 'view', $t->id],
                                    ['class' => 'btn btn-sm btn-outline-secondary'],
                                ) ?>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                <?php if (!$transitions->count()) { ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No transitions found.</td>
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
