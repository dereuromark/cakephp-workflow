<?php
/**
 * @var \Cake\View\View $this
 * @var \Workflow\Model\Entity\WorkflowTimeout $timeout
 * @var \Cake\Datasource\EntityInterface|null $entity
 * @var string|null $currentState
 * @var bool|null $stateMatches
 */
$this->assign('title', 'Timeout Details');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-clock-history me-2"></i>Timeout #<?= h($timeout->id) ?>
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item"><?= $this->Html->link('Timeouts', ['action' => 'index']) ?></li>
                <li class="breadcrumb-item active">#<?= h($timeout->id) ?></li>
            </ol>
        </nav>
    </div>
    <div>
        <?php if (!$timeout->processed) { ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-play-fill"></i> Execute Now',
                ['action' => 'execute', $timeout->id],
                [
                    'class' => 'btn btn-success',
                    'confirm' => 'Execute this timeout now?',
                    'escapeTitle' => false,
                    'block' => true,
                ],
            ) ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-x-circle"></i> Cancel',
                ['action' => 'cancel', $timeout->id],
                [
                    'class' => 'btn btn-outline-danger',
                    'confirm' => 'Cancel this timeout?',
                    'escapeTitle' => false,
                    'block' => true,
                ],
            ) ?>
        <?php } else { ?>
            <?= $this->Form->postLink(
                '<i class="bi bi-arrow-repeat"></i> Retry',
                ['action' => 'retry', $timeout->id],
                [
                    'class' => 'btn btn-warning',
                    'confirm' => 'Create a new timeout to retry?',
                    'escapeTitle' => false,
                    'block' => true,
                ],
            ) ?>
        <?php } ?>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Timeout Details</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">ID</dt>
                    <dd class="col-sm-8"><?= h($timeout->id) ?></dd>

                    <dt class="col-sm-4">Workflow</dt>
                    <dd class="col-sm-8">
                        <?= $this->Html->link(
                            h($timeout->workflow_name),
                            ['controller' => 'Workflows', 'action' => 'view', $timeout->workflow_name],
                        ) ?>
                    </dd>

                    <dt class="col-sm-4">Entity Table</dt>
                    <dd class="col-sm-8"><code><?= h($timeout->entity_table) ?></code></dd>

                    <dt class="col-sm-4">Entity ID</dt>
                    <dd class="col-sm-8">#<?= h($timeout->entity_id) ?></dd>

                    <dt class="col-sm-4">Transition</dt>
                    <dd class="col-sm-8"><code><?= h($timeout->transition_name) ?></code></dd>

                    <dt class="col-sm-4">Expected State</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-secondary"><?= h($timeout->current_state) ?></span>
                    </dd>

                    <dt class="col-sm-4">Due At</dt>
                    <dd class="col-sm-8">
                        <?= h($timeout->due_at) ?>
                        <small class="text-muted">(<?= $timeout->due_at->diffForHumans() ?>)</small>
                    </dd>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">
                        <?php if ($timeout->processed) { ?>
                            <span class="badge bg-secondary">Processed</span>
                        <?php } else { ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php } ?>
                    </dd>

                    <dt class="col-sm-4">Created</dt>
                    <dd class="col-sm-8"><?= h($timeout->created) ?></dd>

                    <dt class="col-sm-4">Modified</dt>
                    <dd class="col-sm-8"><?= h($timeout->modified) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-4 <?= $entity === null ? 'border-danger' : ($stateMatches === false ? 'border-warning' : '') ?>">
            <div class="card-header">
                Entity Status
                <?php if ($entity === null) { ?>
                    <span class="badge bg-danger float-end">Not Found</span>
                <?php } elseif ($stateMatches === false) { ?>
                    <span class="badge bg-warning text-dark float-end">State Changed</span>
                <?php } elseif ($stateMatches === true) { ?>
                    <span class="badge bg-success float-end">Ready</span>
                <?php } ?>
            </div>
            <div class="card-body">
                <?php if ($entity === null) { ?>
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Entity #<?= h($timeout->entity_id) ?> not found in table "<?= h($timeout->entity_table) ?>".
                        The entity may have been deleted.
                    </div>
                <?php } else { ?>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Entity ID</dt>
                        <dd class="col-sm-8">#<?= h($entity->get('id')) ?></dd>

                        <dt class="col-sm-4">Current State</dt>
                        <dd class="col-sm-8">
                            <span class="badge <?= $stateMatches ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= h($currentState ?? 'Unknown') ?>
                            </span>
                        </dd>

                        <dt class="col-sm-4">Expected State</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-secondary"><?= h($timeout->current_state) ?></span>
                        </dd>

                        <dt class="col-sm-4">State Match</dt>
                        <dd class="col-sm-8">
                            <?php if ($stateMatches === true) { ?>
                                <i class="bi bi-check-circle text-success"></i> Yes - Timeout can execute
                            <?php } elseif ($stateMatches === false) { ?>
                                <i class="bi bi-x-circle text-danger"></i> No - State has changed
                            <?php } else { ?>
                                <i class="bi bi-question-circle text-muted"></i> Unknown
                            <?php } ?>
                        </dd>
                    </dl>

                    <?php if ($stateMatches === false) { ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            The entity's state has changed since this timeout was created.
                            Executing this timeout will fail or be skipped.
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>

        <?php if ($entity !== null && $stateMatches === true && !$timeout->processed) { ?>
            <div class="card">
                <div class="card-header">Actions</div>
                <div class="card-body">
                    <p>This timeout is ready to execute. You can:</p>
                    <ul>
                        <li><strong>Execute Now</strong> - Manually trigger the timeout immediately</li>
                        <li><strong>Cancel</strong> - Mark the timeout as processed without executing</li>
                        <li><strong>Wait</strong> - Let the scheduled cron job process it</li>
                    </ul>
                    <?= $this->Html->link(
                        'Simulate Transition',
                        ['controller' => 'Workflows', 'action' => 'simulate', $timeout->workflow_name, $timeout->entity_id, $timeout->transition_name],
                        ['class' => 'btn btn-outline-primary'],
                    ) ?>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
