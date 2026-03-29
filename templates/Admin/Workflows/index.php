<?php
/**
 * @var \Cake\View\View $this
 * @var array<array{name: string, definition: \Workflow\Engine\Definition\Definition, stateCount: int, transitionCount: int}> $workflows
 */
$this->assign('title', 'Workflows');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-collection me-2"></i>All Workflows
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item active">Workflows</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (empty($workflows)) { ?>
    <div class="alert alert-info">
        No workflows configured. Define workflows using PHP 8 Attributes or YAML/NEON configuration.
    </div>
<?php } else { ?>
    <div class="row">
        <?php foreach ($workflows as $workflow) { ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-diagram-2 me-2"></i><?= h($workflow['name']) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($workflow['definition']->getDescription()) { ?>
                            <p class="text-muted"><?= h($workflow['definition']->getDescription()) ?></p>
                        <?php } ?>
                        <table class="table table-sm mb-3">
                            <tr>
                                <th>Table</th>
                                <td><code><?= h($workflow['definition']->getTable()) ?></code></td>
                            </tr>
                            <tr>
                                <th>Field</th>
                                <td><code><?= h($workflow['definition']->getField()) ?></code></td>
                            </tr>
                            <tr>
                                <th>States</th>
                                <td><?= $workflow['stateCount'] ?></td>
                            </tr>
                            <tr>
                                <th>Transitions</th>
                                <td><?= $workflow['transitionCount'] ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="card-footer">
                        <?= $this->Html->link(
                            '<i class="bi bi-eye me-1"></i>View Details',
                            ['action' => 'view', $workflow['name']],
                            ['class' => 'btn btn-primary btn-sm', 'escapeTitle' => false],
                        ) ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
<?php } ?>
