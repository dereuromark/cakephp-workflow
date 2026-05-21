<?php
/**
 * @var \Cake\View\View $this
 * @var \Workflow\Model\Entity\WorkflowTransition $transition
 */
$this->assign('title', 'Transition Details');

$actorLabel = $transition->getActorLabel();
$actorUrl = $transition->getActorUrl();
$runtime = $transition->getRuntime();
$blockedBy = $transition->getBlockedBy();
$error = $transition->getErrorDetails();
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-arrow-left-right me-2"></i>Transition #<?= h($transition->id) ?>
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item"><?= $this->Html->link('Transitions', ['action' => 'index']) ?></li>
                <li class="breadcrumb-item active">#<?= h($transition->id) ?></li>
            </ol>
        </nav>
    </div>
    <div>
        <?= $this->Html->link(
            'View Workflow',
            ['controller' => 'Workflows', 'action' => 'view', $transition->workflow_name],
            ['class' => 'btn btn-outline-primary'],
        ) ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Summary</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Workflow</dt>
                    <dd class="col-sm-8"><?= h($transition->workflow_name) ?></dd>

                    <dt class="col-sm-4">Entity</dt>
                    <dd class="col-sm-8"><?= h($transition->entity_table) ?> #<?= h($transition->entity_id) ?></dd>

                    <dt class="col-sm-4">Transition</dt>
                    <dd class="col-sm-8"><code><?= h($transition->transition_name) ?></code></dd>

                    <dt class="col-sm-4">State Change</dt>
                    <dd class="col-sm-8"><?= h($transition->from_state) ?> -> <?= h($transition->to_state) ?></dd>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8"><span class="badge bg-primary"><?= h($transition->status) ?></span></dd>

                    <dt class="col-sm-4">Reason</dt>
                    <dd class="col-sm-8"><?= h($transition->reason ?: '-') ?></dd>

                    <dt class="col-sm-4">Actor</dt>
                    <dd class="col-sm-8">
                        <?php if ($actorLabel && $actorUrl !== null) { ?>
                            <?= $this->Html->link(h($actorLabel), $actorUrl) ?>
                        <?php } else { ?>
                            <?= h($actorLabel ?? '-') ?>
                        <?php } ?>
                    </dd>

                    <dt class="col-sm-4">Client IP</dt>
                    <dd class="col-sm-8"><?= h($transition->getClientIp() ?: '-') ?></dd>

                    <dt class="col-sm-4">Origin</dt>
                    <dd class="col-sm-8"><?= $transition->isAdminAction() ? 'Admin action' : 'Runtime / automated' ?></dd>

                    <dt class="col-sm-4">Created</dt>
                    <dd class="col-sm-8"><?= h($transition->created) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">Runtime Metadata</div>
            <div class="card-body">
                <?php if (!$runtime) { ?>
                    <p class="text-muted mb-0">No runtime metadata recorded.</p>
                <?php } else { ?>
                    <pre class="mb-0"><code><?= h(json_encode($runtime, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                <?php } ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Blocked By</div>
            <div class="card-body">
                <?php if (!$blockedBy) { ?>
                    <p class="text-muted mb-0">No blockers recorded.</p>
                <?php } else { ?>
                    <pre class="mb-0"><code><?= h(json_encode($blockedBy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                <?php } ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Full Context</div>
            <div class="card-body">
                <pre class="mb-0"><code><?= h(json_encode($transition->context ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                <?php if ($error) { ?>
                    <hr>
                    <h6>Error Details</h6>
                    <pre class="mb-0"><code><?= h(json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
