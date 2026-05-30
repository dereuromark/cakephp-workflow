<?php
/**
 * @var \Cake\View\View $this
 * @var \Workflow\Engine\Definition\Definition $definition
 * @var \Cake\Datasource\EntityInterface $entity
 * @var string $foreignKey
 * @var string $currentState
 * @var array<string, string> $applicableTransitions
 */
$this->assign('title', 'Force Transition');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-lightning me-2"></i>Force Transition
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item"><?= $this->Html->link($definition->getName(), ['action' => 'view', $definition->getName()]) ?></li>
                <li class="breadcrumb-item active">Force #<?= h($foreignKey) ?></li>
            </ol>
        </nav>
    </div>
    <div>
        <?= $this->Html->link(
            '<i class="bi bi-play-circle"></i> Simulate',
            ['action' => 'simulate', $definition->getName(), $foreignKey],
            ['class' => 'btn btn-outline-primary', 'escapeTitle' => false],
        ) ?>
    </div>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">Entity Details</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Workflow</dt>
                    <dd class="col-sm-8"><?= h($definition->getName()) ?></dd>

                    <dt class="col-sm-4">Table</dt>
                    <dd class="col-sm-8"><code><?= h($definition->getTable()) ?></code></dd>

                    <dt class="col-sm-4">Foreign key</dt>
                    <dd class="col-sm-8">#<?= h($foreignKey) ?></dd>

                    <dt class="col-sm-4">Current State</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-primary"><?= h($currentState) ?></span>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-exclamation-triangle me-2"></i>Force Transition
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <strong>Warning:</strong> Forcing a transition bypasses all guards and validations.
                    This should only be used for administrative recovery purposes.
                    The action will be logged with full audit trail.
                </div>

                <?php if (empty($applicableTransitions)) { ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        No transitions are defined from the current state "<?= h($currentState) ?>".
                        This entity may be in a final state or the workflow definition has no outgoing transitions.
                    </div>
                <?php } else { ?>
                    <?= $this->Form->create(null, ['url' => ['action' => 'forceTransition', $definition->getName(), $foreignKey]]) ?>

                    <?= $this->Form->control('transition', [
                        'type' => 'select',
                        'options' => $applicableTransitions,
                        'empty' => '-- Select Transition --',
                        'label' => 'Transition to Force',
                        'class' => 'form-select',
                        'required' => true,
                    ]) ?>

                    <?= $this->Form->control('reason', [
                        'type' => 'textarea',
                        'label' => 'Reason (Required)',
                        'placeholder' => 'Explain why this transition is being forced...',
                        'class' => 'form-control',
                        'rows' => 3,
                        'required' => true,
                    ]) ?>

                    <div class="mt-3">
                        <?= $this->Form->button('<i class="bi bi-lightning"></i> Force Transition', [
                            'class' => 'btn btn-warning',
                            'type' => 'submit',
                            'escapeTitle' => false,
                            'onclick' => "return confirm('Are you sure you want to force this transition? This action bypasses all guards and validations.');",
                        ]) ?>
                        <?= $this->Html->link('Cancel', ['action' => 'view', $definition->getName()], [
                            'class' => 'btn btn-outline-secondary',
                        ]) ?>
                    </div>

                    <?= $this->Form->end() ?>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($applicableTransitions)) { ?>
    <div class="card mt-4">
        <div class="card-header">Available Transitions from "<?= h($currentState) ?>"</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Transition</th>
                        <th>Target State</th>
                        <th>Guards (will be bypassed)</th>
                        <th>Commands (will NOT execute)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($definition->getTransitions() as $t) {
                        if (!in_array($currentState, $t->getFrom(), true)) {
                            continue;
                        }
                    ?>
                        <tr>
                            <td>
                                <strong><?= h($t->getDisplayName()) ?></strong>
                                <?php if ($t->getLabel() !== null) { ?>
                                    <code class="small"><?= h($t->getName()) ?></code>
                                <?php } ?>
                            </td>
                            <td><span class="badge bg-dark"><?= h($t->getTo()) ?></span></td>
                            <td>
                                <?php if ($t->getGuards()) { ?>
                                    <?php foreach ($t->getGuards() as $guard) { ?>
                                        <code class="small text-danger"><?= h($guard) ?></code><br>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span class="text-muted">None</span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($t->getCommands()) { ?>
                                    <?php foreach ($t->getCommands() as $command) { ?>
                                        <code class="small text-warning"><?= h($command) ?></code><br>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span class="text-muted">None</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer text-muted small">
            <i class="bi bi-info-circle me-1"></i>
            Forcing a transition only changes the state field. Guards are bypassed and commands are NOT executed.
            If you need commands to run, use the normal workflow transition instead.
        </div>
    </div>
<?php } ?>
