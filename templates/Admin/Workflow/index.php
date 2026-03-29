<?php
/**
 * @var \Cake\View\View $this
 * @var array<array{name: string, definition: \Workflow\Engine\Definition\Definition, stateCounts: array<string, int|null>}> $workflows
 * @var int $totalActiveItems
 * @var int $transitionsToday
 * @var array<\Workflow\Model\Entity\WorkflowTimeout> $pendingTimeouts
 * @var array<\Workflow\Model\Entity\WorkflowTransition> $recentTransitions
 * @var int $orphanCount
 */

use Cake\I18n\DateTime;

$this->assign('title', 'Dashboard');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">Dashboard</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item active">Workflow</li>
            </ol>
        </nav>
    </div>
    <div>
        <?= $this->Html->link(
            '<i class="bi bi-plus-lg me-1"></i>New Workflow',
            ['controller' => 'Workflows', 'action' => 'create'],
            ['class' => 'btn btn-primary', 'escapeTitle' => false],
        ) ?>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value"><?= $totalActiveItems ?></div>
            <div class="stat-label">Total Active Items</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-success"><?= $transitionsToday ?></div>
            <div class="stat-label">Transitions Today</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value text-warning"><?= count($pendingTimeouts) ?></div>
            <div class="stat-label">Pending Timeouts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value <?= $orphanCount > 0 ? 'text-danger' : '' ?>"><?= $orphanCount ?></div>
            <div class="stat-label">Orphaned Items</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Workflow Cards -->
    <div class="col-lg-8">
        <h5 class="mb-3">Workflows</h5>

        <?php if (empty($workflows)) { ?>
            <div class="alert alert-info">
                No workflows configured. Define workflows using PHP 8 Attributes or YAML/NEON configuration.
            </div>
        <?php } ?>

        <?php foreach ($workflows as $workflow) { ?>
            <div class="workflow-card">
                <h5>
                    <span><i class="bi bi-diagram-2 me-2"></i><?= h($workflow['name']) ?></span>
                    <?= $this->Html->link(
                        'View Details',
                        ['controller' => 'Workflows', 'action' => 'view', $workflow['name']],
                        ['class' => 'btn btn-sm btn-outline-primary'],
                    ) ?>
                </h5>
                <?php if ($workflow['definition']->getDescription()) { ?>
                    <p class="text-muted mb-3"><?= h($workflow['definition']->getDescription()) ?></p>
                <?php } ?>

                <div class="mb-3">
                    <?php foreach ($workflow['definition']->getStates() as $state) { ?>
                        <?php
                        $count = $workflow['stateCounts'][$state->getName()] ?? null;
                        $color = $this->Workflow->getStateColor($workflow['definition'], $state->getName());
                        $badgeClass = 'state-badge';
                        if ($state->isInitial()) {
                            $badgeClass .= ' bg-warning text-dark';
                        } elseif ($state->isFailed()) {
                            $badgeClass .= ' bg-danger text-white';
                        } elseif ($state->isFinal()) {
                            $badgeClass .= ' bg-secondary text-white';
                        } else {
                            $badgeClass .= ' bg-light text-dark';
                        }
                        ?>
                        <span class="<?= $badgeClass ?>">
                            <span class="item-state-highlight" style="background:<?= h($color) ?>"></span>
                            <?= h($state->getName()) ?>
                            <span class="count"><?= $count !== null ? $count : '-' ?></span>
                        </span>
                    <?php } ?>
                </div>

                <?php
                $flags = [];
                foreach ($workflow['definition']->getStates() as $state) {
                    foreach ($state->getFlags() as $flag) {
                        if (!isset($flags[$flag])) {
                            $flags[$flag] = 0;
                        }
                        $count = $workflow['stateCounts'][$state->getName()] ?? 0;
                        if ($count !== null) {
                            $flags[$flag] += $count;
                        }
                    }
                }
                ?>
                <?php if ($flags) { ?>
                    <div class="d-flex gap-2">
                        <?php foreach ($flags as $flag => $count) { ?>
                            <span class="badge bg-light text-dark"><i class="bi bi-flag"></i> <?= h($flag) ?>: <?= $count ?></span>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <!-- Right Column -->
    <div class="col-lg-4">
        <!-- Pending Timeouts -->
        <h5 class="mb-3">Pending Timeouts</h5>
        <div class="mb-4">
            <?php if (empty($pendingTimeouts)) { ?>
                <p class="text-muted">No pending timeouts.</p>
            <?php } else { ?>
                <?php foreach ($pendingTimeouts as $timeout) { ?>
                    <?php
                    $dueAt = $timeout->due_at;
                    $now = DateTime::now();
                    if ($dueAt < $now) {
                        $timeText = 'overdue';
                    } else {
                        $timeText = 'in ' . $dueAt->diffForHumans($now, true);
                    }
                    ?>
                    <div class="timeout-warning">
                        <div class="d-flex justify-content-between">
                            <strong><?= h($timeout->workflow_name) ?> #<?= h($timeout->entity_id) ?></strong>
                            <small class="text-muted"><?= $timeText ?></small>
                        </div>
                        <small><?= h($timeout->current_state) ?> &rarr; <?= h($timeout->transition_name) ?></small>
                    </div>
                <?php } ?>
                <?= $this->Html->link(
                    'View All Timeouts',
                    ['controller' => 'Timeouts', 'action' => 'index'],
                    ['class' => 'btn btn-sm btn-outline-warning w-100 mt-2'],
                ) ?>
            <?php } ?>
        </div>

        <!-- Recent Transitions -->
        <h5 class="mb-3">Recent Transitions</h5>
        <div class="recent-transitions">
            <?php if (empty($recentTransitions)) { ?>
                <div class="p-3 text-muted">No transitions recorded yet.</div>
            <?php } else { ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentTransitions as $t) { ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?= h($t->workflow_name) ?> #<?= h($t->entity_id) ?></strong>
                                    <div class="small">
                                        <span class="text-secondary"><?= h($t->from_state) ?></span>
                                        <i class="bi bi-arrow-right transition-arrow"></i>
                                        <span class="text-primary"><?= h($t->to_state) ?></span>
                                    </div>
                                </div>
                                <small class="text-muted"><?= $t->created->diffForHumans() ?></small>
                            </div>
                            <?php if ($t->user_id) { ?>
                                <small class="text-muted">by <?= h($t->user_id) ?></small>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <div class="list-group-item bg-light">
                        <?= $this->Html->link(
                            'View all transitions &rarr;',
                            ['controller' => 'Transitions', 'action' => 'index'],
                            ['escapeTitle' => false, 'class' => 'text-decoration-none'],
                        ) ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
