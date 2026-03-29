<?php
/**
 * @var \Cake\View\View $this
 * @var \Workflow\Engine\Definition\Definition $definition
 * @var array<array{type: string, severity: string, message: string, context: array<string, mixed>}> $issues
 * @var array{errors: array<array{type: string, severity: string, message: string, context: array<string, mixed>}>, warnings: array<array{type: string, severity: string, message: string, context: array<string, mixed>}>, info: array<array{type: string, severity: string, message: string, context: array<string, mixed>}>} $issuesBySeverity
 * @var int $errorCount
 * @var int $warningCount
 * @var int $infoCount
 */
$this->assign('title', $definition->getName() . ' - Validate');

$totalIssues = $errorCount + $warningCount + $infoCount;
$isValid = $errorCount === 0;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-check2-circle me-2"></i><?= h($definition->getName()) ?> - Validation
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item"><?= $this->Html->link('Workflows', ['action' => 'index']) ?></li>
                <li class="breadcrumb-item"><?= $this->Html->link($definition->getName(), ['action' => 'view', $definition->getName()]) ?></li>
                <li class="breadcrumb-item active">Validate</li>
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

<!-- Summary Card -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card <?= $isValid ? 'border-success' : 'border-danger' ?>">
            <div class="card-body text-center">
                <?php if ($isValid) { ?>
                    <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
                    <h4 class="mt-2 text-success">Workflow Valid</h4>
                    <p class="text-muted mb-0">No errors found</p>
                <?php } else { ?>
                    <i class="bi bi-x-circle-fill text-danger" style="font-size:3rem"></i>
                    <h4 class="mt-2 text-danger">Validation Failed</h4>
                    <p class="text-muted mb-0"><?= $errorCount ?> error(s) found</p>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header">Issue Summary</div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="display-6 <?= $errorCount > 0 ? 'text-danger' : 'text-muted' ?>"><?= $errorCount ?></div>
                        <small class="text-muted">Errors</small>
                    </div>
                    <div class="col-4">
                        <div class="display-6 <?= $warningCount > 0 ? 'text-warning' : 'text-muted' ?>"><?= $warningCount ?></div>
                        <small class="text-muted">Warnings</small>
                    </div>
                    <div class="col-4">
                        <div class="display-6 <?= $infoCount > 0 ? 'text-info' : 'text-muted' ?>"><?= $infoCount ?></div>
                        <small class="text-muted">Info</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($totalIssues === 0) { ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-2"></i>
        No issues detected. This workflow definition is valid and well-structured.
    </div>
<?php } else { ?>
    <!-- Errors -->
    <?php if ($errorCount > 0) { ?>
        <div class="card mb-4 border-danger">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-x-circle me-2"></i>Errors (<?= $errorCount ?>)
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($issuesBySeverity['errors'] as $issue) { ?>
                        <li class="list-group-item">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-x-circle-fill text-danger me-3 mt-1"></i>
                                <div>
                                    <strong><?= h($issue['message']) ?></strong>
                                    <div class="small text-muted">Type: <?= h($issue['type']) ?></div>
                                </div>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    <?php } ?>

    <!-- Warnings -->
    <?php if ($warningCount > 0) { ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>Warnings (<?= $warningCount ?>)
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($issuesBySeverity['warnings'] as $issue) { ?>
                        <li class="list-group-item">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-exclamation-triangle-fill text-warning me-3 mt-1"></i>
                                <div>
                                    <strong><?= h($issue['message']) ?></strong>
                                    <div class="small text-muted">Type: <?= h($issue['type']) ?></div>
                                </div>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    <?php } ?>

    <!-- Info -->
    <?php if ($infoCount > 0) { ?>
        <div class="card mb-4 border-info">
            <div class="card-header bg-info text-white">
                <i class="bi bi-info-circle me-2"></i>Info (<?= $infoCount ?>)
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($issuesBySeverity['info'] as $issue) { ?>
                        <li class="list-group-item">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-info-circle-fill text-info me-3 mt-1"></i>
                                <div>
                                    <strong><?= h($issue['message']) ?></strong>
                                    <div class="small text-muted">Type: <?= h($issue['type']) ?></div>
                                </div>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    <?php } ?>
<?php } ?>

<!-- Validation Checks -->
<div class="card">
    <div class="card-header">Validation Checks Performed</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Initial state defined</li>
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Final states defined</li>
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>All states reachable</li>
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>No dead-end states</li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Valid transition targets</li>
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>No duplicate transitions</li>
                    <li class="mb-2"><i class="bi bi-check text-success me-2"></i>Happy path completeness</li>
                </ul>
            </div>
        </div>
    </div>
</div>
