<?php
/**
 * @var \Cake\View\View $this
 * @var \Workflow\Engine\Definition\Definition $definition
 * @var array<string, int> $stateCounts
 * @var int $totalActive
 * @var array<\Workflow\Model\Entity\WorkflowTransition> $recentTransitions
 * @var int $transitionsToday
 * @var array<\Workflow\Model\Entity\WorkflowTimeout> $pendingTimeouts
 */
$this->assign('title', $definition->getName());

$stateCount = count($definition->getStates());
$transitionCount = count($definition->getTransitions());
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-diagram-2 me-2"></i><?= h($definition->getName()) ?>
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item"><?= $this->Html->link('Workflows', ['action' => 'index']) ?></li>
                <li class="breadcrumb-item active"><?= h($definition->getName()) ?></li>
            </ol>
        </nav>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link active" href="#">Overview</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#">States (<?= $stateCount ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#">Transitions (<?= $transitionCount ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#">Items (<?= $totalActive ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#">History</a>
    </li>
</ul>

<div class="row">
    <!-- Diagram -->
    <div class="col-lg-8">
        <div class="diagram-container mb-4">
            <div class="d-flex gap-2 mb-3">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary active">Diagram</button>
                    <button class="btn btn-outline-secondary">Code</button>
                </div>
                <div class="btn-group btn-group-sm ms-auto">
                    <button class="btn btn-outline-secondary" title="Zoom In"><i class="bi bi-zoom-in"></i></button>
                    <button class="btn btn-outline-secondary" title="Zoom Out"><i class="bi bi-zoom-out"></i></button>
                    <button class="btn btn-outline-secondary" title="Fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
                </div>
            </div>
            <?= $this->Workflow->diagram($definition) ?>
            <div class="mt-3 text-center">
                <small class="text-muted">
                    <span class="badge bg-success me-2">●</span> Happy path
                    <span class="badge bg-secondary ms-3 me-2">●</span> Normal transition
                </small>
            </div>
        </div>

        <!-- States Table -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>States</span>
                <span class="badge bg-secondary"><?= $stateCount ?> states</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:40px"></th>
                            <th>Name</th>
                            <th>Label</th>
                            <th>Flags</th>
                            <th>Type</th>
                            <th class="text-end">Items</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($definition->getStates() as $state) { ?>
                            <?php
                            $color = $this->Workflow->getStateColor($definition, $state->getName());
                            $count = $stateCounts[$state->getName()] ?? 0;
                            ?>
                            <tr>
                                <td><span class="state-color" style="background:<?= h($color) ?>;width:20px;height:20px;border-radius:4px;display:inline-block"></span></td>
                                <td><code><?= h($state->getName()) ?></code></td>
                                <td><?= h($state->getLabel() ?? '-') ?></td>
                                <td>
                                    <?php if ($state->getFlags()) { ?>
                                        <?php foreach ($state->getFlags() as $flag) { ?>
                                            <span class="flag-badge"><?= h($flag) ?></span>
                                        <?php } ?>
                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if ($state->isInitial()) { ?>
                                        <span class="badge bg-info">Initial</span>
                                    <?php } ?>
                                    <?php if ($state->isFailed()) { ?>
                                        <span class="badge bg-danger">Failed</span>
                                    <?php } elseif ($state->isFinal()) { ?>
                                        <span class="badge bg-dark">Final</span>
                                    <?php } ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($state->isFinal()) { ?>
                                        <span class="text-muted">-</span>
                                    <?php } else { ?>
                                        <a href="#"><?= $count ?></a>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transitions Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Transitions</span>
                <span class="badge bg-secondary"><?= $transitionCount ?> transitions</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>From</th>
                            <th></th>
                            <th>To</th>
                            <th>Guards</th>
                            <th>Commands</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($definition->getTransitions() as $transition) { ?>
                            <tr>
                                <td>
                                    <code><?= h($transition->getName()) ?></code>
                                    <?php if ($transition->isHappy()) { ?>
                                        <i class="bi bi-star-fill happy-path" title="Happy path"></i>
                                    <?php } ?>
                                    <?php if ($transition->isAutomatic()) { ?>
                                        <i class="bi bi-lightning-fill text-warning" title="Automatic transition"></i>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php foreach ($transition->getFrom() as $from) { ?>
                                        <?= $this->Workflow->stateBadge($definition, $from) ?>
                                    <?php } ?>
                                </td>
                                <td><i class="bi bi-arrow-right"></i></td>
                                <td>
                                    <?= $this->Workflow->stateBadge($definition, $transition->getTo()) ?>
                                </td>
                                <td>
                                    <?php if ($transition->getGuards()) { ?>
                                        <?php foreach ($transition->getGuards() as $guard) { ?>
                                            <code class="small"><?= h($guard) ?></code>
                                        <?php } ?>
                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if ($transition->getCommands()) { ?>
                                        <?php foreach ($transition->getCommands() as $command) { ?>
                                            <code class="small"><?= h($command) ?></code>
                                        <?php } ?>
                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sidebar Info -->
    <div class="col-lg-4">
        <!-- Workflow Info -->
        <div class="card mb-4">
            <div class="card-header">Workflow Info</div>
            <div class="card-body p-0">
                <table class="table table-sm info-table mb-0">
                    <tr>
                        <th>Name</th>
                        <td><code><?= h($definition->getName()) ?></code></td>
                    </tr>
                    <tr>
                        <th>Type</th>
                        <td>State Machine</td>
                    </tr>
                    <tr>
                        <th>Table</th>
                        <td><code><?= h($definition->getTable()) ?></code></td>
                    </tr>
                    <tr>
                        <th>Field</th>
                        <td><code><?= h($definition->getField()) ?></code></td>
                    </tr>
                    <tr>
                        <th>Version</th>
                        <td><code><?= h($definition->getVersionHash()) ?></code></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="card mb-4">
            <div class="card-header">Statistics</div>
            <div class="card-body p-0">
                <table class="table table-sm info-table mb-0">
                    <tr>
                        <th>Active Items</th>
                        <td><strong><?= $totalActive ?></strong></td>
                    </tr>
                    <tr>
                        <th>Today</th>
                        <td>+<?= $transitionsToday ?> transitions</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Timeouts -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <span>Pending Timeouts</span>
                <span class="badge bg-secondary"><?= count($pendingTimeouts) ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($pendingTimeouts)) { ?>
                    <p class="text-muted mb-0">No pending timeouts.</p>
                <?php } else { ?>
                    <?php foreach ($pendingTimeouts as $timeout) { ?>
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-3">
                                <i class="bi bi-clock-history text-warning" style="font-size:1.5rem"></i>
                            </div>
                            <div>
                                <strong><?= h($timeout->entity_table) ?> #<?= h($timeout->entity_id) ?>: <?= h($timeout->transition_name) ?></strong>
                                <div class="small text-muted">Due: <?= $timeout->due_at->nice() ?></div>
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>

        <!-- Recent Transitions -->
        <div class="card mb-4">
            <div class="card-header">Recent Transitions</div>
            <div class="card-body">
                <?php if (empty($recentTransitions)) { ?>
                    <p class="text-muted mb-0">No transitions recorded yet.</p>
                <?php } else { ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach (array_slice($recentTransitions, 0, 5) as $t) { ?>
                            <li class="mb-2">
                                <small class="text-muted"><?= $t->created->diffForHumans() ?></small><br>
                                <strong><?= h($t->entity_table) ?> #<?= h($t->entity_id) ?></strong>:
                                <?= h($t->from_state) ?> &rarr; <?= h($t->to_state) ?>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <div class="card-header">Quick Actions</div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?= $this->Html->link(
                        '<i class="bi bi-list-check me-2"></i>View All Items',
                        '#',
                        ['class' => 'btn btn-outline-primary', 'escapeTitle' => false],
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-arrow-left-right me-2"></i>View Transitions',
                        ['controller' => 'Transitions', 'action' => 'index', '?' => ['workflow' => $definition->getName()]],
                        ['class' => 'btn btn-outline-secondary', 'escapeTitle' => false],
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-clock-history me-2"></i>View Timeouts',
                        ['controller' => 'Timeouts', 'action' => 'index', '?' => ['workflow' => $definition->getName()]],
                        ['class' => 'btn btn-outline-warning', 'escapeTitle' => false],
                    ) ?>
                </div>
            </div>
        </div>
    </div>
</div>
