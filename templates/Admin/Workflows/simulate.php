<?php
/**
 * @var \Cake\View\View $this
 * @var \Workflow\Engine\Definition\Definition $definition
 * @var \Cake\Datasource\EntityInterface $entity
 * @var string $entityId
 * @var string $currentState
 * @var array<array<string, mixed>> $simulationResults
 * @var array<string> $availableTransitions
 * @var string|null $transition
 */
$this->assign('title', 'Simulate Transitions');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-play-circle me-2"></i>Simulate Transitions
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item"><?= $this->Html->link($definition->getName(), ['action' => 'view', $definition->getName()]) ?></li>
                <li class="breadcrumb-item active">Simulate #<?= h($entityId) ?></li>
            </ol>
        </nav>
    </div>
    <div>
        <?= $this->Html->link(
            '<i class="bi bi-lightning"></i> Force Transition',
            ['action' => 'forceTransition', $definition->getName(), $entityId],
            ['class' => 'btn btn-warning', 'escapeTitle' => false],
        ) ?>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Entity Details</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Table</dt>
                    <dd class="col-sm-7"><code><?= h($definition->getTable()) ?></code></dd>

                    <dt class="col-sm-5">Entity ID</dt>
                    <dd class="col-sm-7">#<?= h($entityId) ?></dd>

                    <dt class="col-sm-5">Current State</dt>
                    <dd class="col-sm-7">
                        <span class="badge bg-primary"><?= h($currentState) ?></span>
                    </dd>

                    <dt class="col-sm-5">Available</dt>
                    <dd class="col-sm-7">
                        <?php if ($availableTransitions) { ?>
                            <?php foreach ($availableTransitions as $t) { ?>
                                <span class="badge bg-success"><?= h($t) ?></span>
                            <?php } ?>
                        <?php } else { ?>
                            <span class="text-muted">None</span>
                        <?php } ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Simulation Summary</div>
            <div class="card-body">
                <?php
                $canApplyCount = count(array_filter($simulationResults, fn ($r) => $r['can_apply']));
                $blockedCount = count($simulationResults) - $canApplyCount;
                ?>
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="h3 text-success mb-0"><?= $canApplyCount ?></div>
                        <small class="text-muted">Can Apply</small>
                    </div>
                    <div class="col-md-4">
                        <div class="h3 text-danger mb-0"><?= $blockedCount ?></div>
                        <small class="text-muted">Blocked</small>
                    </div>
                    <div class="col-md-4">
                        <div class="h3 mb-0"><?= count($simulationResults) ?></div>
                        <small class="text-muted">Total Transitions</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <?php if ($transition) { ?>
            Simulation for "<?= h($transition) ?>"
        <?php } else { ?>
            All Transitions Simulation
        <?php } ?>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Transition</th>
                    <th>From States</th>
                    <th>To State</th>
                    <th>From Valid?</th>
                    <th>Guards</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($simulationResults as $result) { ?>
                    <tr class="<?= $result['can_apply'] ? 'table-success' : ($result['is_from_state_valid'] ? 'table-warning' : 'table-secondary') ?>">
                        <td>
                            <strong><?= h($result['name']) ?></strong>
                            <?php if ($result['is_automatic']) { ?>
                                <span class="badge bg-info">auto</span>
                            <?php } ?>
                            <?php if ($result['is_happy']) { ?>
                                <span class="badge bg-success">happy</span>
                            <?php } ?>
                        </td>
                        <td>
                            <?php foreach ($result['from'] as $from) { ?>
                                <span class="badge <?= $from === $currentState ? 'bg-primary' : 'bg-secondary' ?>">
                                    <?= h($from) ?>
                                </span>
                            <?php } ?>
                        </td>
                        <td>
                            <span class="badge bg-dark"><?= h($result['to']) ?></span>
                        </td>
                        <td>
                            <?php if ($result['is_from_state_valid']) { ?>
                                <i class="bi bi-check-circle text-success"></i> Yes
                            <?php } else { ?>
                                <i class="bi bi-x-circle text-danger"></i> No
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($result['guards']) { ?>
                                <?php foreach ($result['guards'] as $guard) { ?>
                                    <code class="small"><?= h($guard) ?></code><br>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="text-muted">None</span>
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($result['can_apply']) { ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-check"></i> Can Apply
                                </span>
                            <?php } elseif (!$result['is_from_state_valid']) { ?>
                                <span class="badge bg-secondary">
                                    <i class="bi bi-dash"></i> Wrong State
                                </span>
                            <?php } else { ?>
                                <span class="badge bg-danger">
                                    <i class="bi bi-x"></i> Blocked by Guards
                                </span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">Legend</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <span class="badge bg-success me-2">&nbsp;</span>
                <strong>Can Apply</strong> - Transition can be executed
            </div>
            <div class="col-md-4">
                <span class="badge bg-warning me-2">&nbsp;</span>
                <strong>Blocked</strong> - From state valid, but guards blocking
            </div>
            <div class="col-md-4">
                <span class="badge bg-secondary me-2">&nbsp;</span>
                <strong>Wrong State</strong> - Entity not in required from state
            </div>
        </div>
    </div>
</div>
