<?php
/**
 * @var \Cake\View\View $this
 * @var \Workflow\Engine\Definition\Definition $definition
 * @var array<string, array<string, int>> $matrix
 * @var array<string, int> $timeBuckets
 * @var array<string, int> $totals
 * @var array<string, int> $stateTotals
 */
$this->assign('title', $definition->getName() . ' - Matrix');

$bucketNames = array_keys($timeBuckets);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-grid-3x3 me-2"></i><?= h($definition->getName()) ?> - Matrix
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item"><?= $this->Html->link('Workflows', ['action' => 'index']) ?></li>
                <li class="breadcrumb-item"><?= $this->Html->link($definition->getName(), ['action' => 'view', $definition->getName()]) ?></li>
                <li class="breadcrumb-item active">Matrix</li>
            </ol>
        </nav>
    </div>
    <div>
        <?= $this->Html->link(
            '<i class="bi bi-diagram-2 me-1"></i>View Diagram',
            ['action' => 'view', $definition->getName()],
            ['class' => 'btn btn-outline-primary', 'escapeTitle' => false],
        ) ?>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-2"></i>Time in State Matrix</span>
        <small class="text-muted">Shows how long items have been in each state</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 matrix-table">
                <thead class="table-light">
                    <tr>
                        <th class="state-column">State</th>
                        <?php foreach ($bucketNames as $bucket) { ?>
                            <th class="text-center bucket-column"><?= h($bucket) ?></th>
                        <?php } ?>
                        <th class="text-center total-column">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($definition->getStates() as $state) { ?>
                        <?php
                        $stateName = $state->getName();
                        $stateTotal = $stateTotals[$stateName] ?? 0;
                        $color = $this->Workflow->getStateColor($definition, $stateName);
                        $rowClass = '';
                        if ($state->isFinal()) {
                            $rowClass = 'table-secondary';
                        } elseif ($state->isFailed()) {
                            $rowClass = 'table-danger';
                        }
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td>
                                <span class="state-indicator" style="background:<?= h($color) ?>"></span>
                                <strong><?= h($stateName) ?></strong>
                                <?php if ($state->isInitial()) { ?>
                                    <span class="badge bg-info ms-1">Initial</span>
                                <?php } ?>
                                <?php if ($state->isFinal()) { ?>
                                    <span class="badge bg-secondary ms-1">Final</span>
                                <?php } ?>
                            </td>
                            <?php foreach ($bucketNames as $bucket) { ?>
                                <?php
                                $count = $matrix[$stateName][$bucket] ?? 0;
                                $cellClass = '';
                                // Highlight cells with items that might be stuck
                                if ($count > 0 && !$state->isFinal()) {
                                    if ($bucket === '> 7 days') {
                                        $cellClass = 'table-danger';
                                    } elseif ($bucket === '1-7 days') {
                                        $cellClass = 'table-warning';
                                    }
                                }
                                ?>
                                <td class="text-center <?= $cellClass ?>">
                                    <?php if ($count > 0) { ?>
                                        <?= $this->Html->link(
                                            (string) $count,
                                            ['controller' => 'Transitions', 'action' => 'index', '?' => ['workflow' => $definition->getName()]],
                                            ['class' => 'matrix-cell-link'],
                                        ) ?>
                                    <?php } else { ?>
                                        <span class="text-muted">-</span>
                                    <?php } ?>
                                </td>
                            <?php } ?>
                            <td class="text-center total-column">
                                <strong><?= $stateTotal ?></strong>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th>Total</th>
                        <?php foreach ($bucketNames as $bucket) { ?>
                            <th class="text-center"><?= $totals[$bucket] ?? 0 ?></th>
                        <?php } ?>
                        <th class="text-center"><?= array_sum($stateTotals) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Legend</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-3">
                    <div>
                        <span class="badge bg-danger">&nbsp;</span>
                        <small class="ms-1">Items stuck > 7 days</small>
                    </div>
                    <div>
                        <span class="badge bg-warning">&nbsp;</span>
                        <small class="ms-1">Items waiting 1-7 days</small>
                    </div>
                    <div>
                        <span class="badge bg-secondary">&nbsp;</span>
                        <small class="ms-1">Final states (completed)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Quick Actions</div>
            <div class="card-body">
                <div class="d-flex gap-2">
                    <?= $this->Html->link(
                        '<i class="bi bi-arrow-left-right me-1"></i>View Transitions',
                        ['controller' => 'Transitions', 'action' => 'index', '?' => ['workflow' => $definition->getName()]],
                        ['class' => 'btn btn-outline-secondary btn-sm', 'escapeTitle' => false],
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-clock-history me-1"></i>View Timeouts',
                        ['controller' => 'Timeouts', 'action' => 'index', '?' => ['workflow' => $definition->getName()]],
                        ['class' => 'btn btn-outline-warning btn-sm', 'escapeTitle' => false],
                    ) ?>
                </div>
            </div>
        </div>
    </div>
</div>
