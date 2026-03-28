<?php
/**
 * @var \Cake\View\View $this
 * @var \Workflow\Engine\Definition\Definition $definition
 * @var array<\Workflow\Model\Entity\WorkflowTransition> $recentTransitions
 * @var array<\Workflow\Model\Entity\WorkflowTimeout> $pendingTimeouts
 */
$this->assign('title', $definition->getName());
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <?= $this->Html->link('Workflows', ['action' => 'index']) ?>
                </li>
                <li class="breadcrumb-item active"><?= h($definition->getName()) ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">State Diagram</h5>
            </div>
            <div class="card-body">
                <?= $this->Workflow->diagram($definition) ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">States</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Label</th>
                            <th>Type</th>
                            <th>Flags</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($definition->getStates() as $state) { ?>
                            <tr>
                                <td>
                                    <?= $this->Workflow->stateBadge($definition, $state->getName()) ?>
                                </td>
                                <td><?= h($state->getLabel() ?? '-') ?></td>
                                <td>
                                    <?php if ($state->isInitial()) { ?>
                                        <span class="badge bg-info">Initial</span>
                                    <?php } ?>
                                    <?php if ($state->isFailed()) { ?>
                                        <span class="badge bg-danger">Failed</span>
                                    <?php } elseif ($state->isFinal()) { ?>
                                        <span class="badge bg-secondary">Final</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php foreach ($state->getFlags() as $flag) { ?>
                                        <span class="badge bg-light text-dark"><?= h($flag) ?></span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Transitions</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Guards</th>
                            <th>Commands</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($definition->getTransitions() as $transition) { ?>
                            <tr>
                                <td>
                                    <?= h($transition->getName()) ?>
                                    <?php if ($transition->isHappy()) { ?>
                                        <span class="badge bg-success">Happy</span>
                                    <?php } ?>
                                </td>
                                <td><?= h(implode(', ', $transition->getFrom())) ?></td>
                                <td><?= h($transition->getTo()) ?></td>
                                <td><?= h(implode(', ', $transition->getGuards()) ?: '-') ?></td>
                                <td><?= h(implode(', ', $transition->getCommands()) ?: '-') ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Recent Transitions</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentTransitions)) { ?>
                    <p class="text-muted">No transitions recorded yet.</p>
                <?php } else { ?>
                    <ul class="list-unstyled">
                        <?php foreach ($recentTransitions as $t) { ?>
                            <li class="mb-2">
                                <small class="text-muted"><?= $t->created->nice() ?></small><br>
                                <strong><?= h($t->transition_name) ?></strong>:
                                <?= h($t->from_state) ?> → <?= h($t->to_state) ?>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Pending Timeouts</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pendingTimeouts)) { ?>
                    <p class="text-muted">No pending timeouts.</p>
                <?php } else { ?>
                    <ul class="list-unstyled">
                        <?php foreach ($pendingTimeouts as $timeout) { ?>
                            <li class="mb-2">
                                <small class="text-muted">Due: <?= $timeout->due_at->nice() ?></small><br>
                                Entity #<?= h($timeout->entity_id) ?>:
                                <?= h($timeout->transition_name) ?>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
