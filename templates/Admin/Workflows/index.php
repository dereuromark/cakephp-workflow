<?php
/**
 * @var \Cake\View\View $this
 * @var array<array{name: string, definition: \Workflow\Engine\Definition\Definition, stateCount: int, transitionCount: int}> $workflows
 */
$this->assign('title', 'Workflows');
?>

<div class="row">
    <div class="col-12">
        <h1>Workflows</h1>
    </div>
</div>

<div class="row mt-4">
    <?php foreach ($workflows as $workflow) { ?>
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?= h($workflow['name']) ?></h5>
                </div>
                <div class="card-body">
                    <p class="card-text">
                        <strong>Table:</strong> <?= h($workflow['definition']->getTable()) ?><br>
                        <strong>Field:</strong> <?= h($workflow['definition']->getField()) ?><br>
                        <strong>States:</strong> <?= $workflow['stateCount'] ?><br>
                        <strong>Transitions:</strong> <?= $workflow['transitionCount'] ?>
                    </p>
                    <?= $this->Html->link(
                        'View Details',
                        ['action' => 'view', $workflow['name']],
                        ['class' => 'btn btn-primary'],
                    ) ?>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<?php if (empty($workflows)) { ?>
    <div class="alert alert-info">
        No workflows configured. Define workflows using PHP 8 Attributes or YAML configuration.
    </div>
<?php } ?>
