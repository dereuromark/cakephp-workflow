<?php
/**
 * @var \Cake\View\View $this
 * @var \Workflow\Engine\Definition\Definition $definition
 * @var array<array{name: string, label: ?string, color: ?string, isInitial: bool, isFinal: bool, isFailed: bool, flags: array<string>}> $states
 * @var array<array{name: string, from: array<string>, to: string, isHappy: bool, isAutomatic: bool, guards: array<string>, commands: array<string>}> $transitions
 */
$this->assign('title', $definition->getName() . ' - Designer');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-pencil-square me-2"></i><?= h($definition->getName()) ?> - Designer
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item"><?= $this->Html->link('Workflows', ['action' => 'index']) ?></li>
                <li class="breadcrumb-item"><?= $this->Html->link($definition->getName(), ['action' => 'view', $definition->getName()]) ?></li>
                <li class="breadcrumb-item active">Designer</li>
            </ol>
        </nav>
    </div>
    <div>
        <?= $this->Html->link(
            '<i class="bi bi-diagram-2 me-1"></i>View Diagram',
            ['action' => 'view', $definition->getName()],
            ['class' => 'btn btn-outline-primary', 'escapeTitle' => false],
        ) ?>
        <?= $this->Html->link(
            '<i class="bi bi-check2-circle me-1"></i>Validate',
            ['action' => 'validate', $definition->getName()],
            ['class' => 'btn btn-outline-secondary', 'escapeTitle' => false],
        ) ?>
    </div>
</div>

<div class="row">
    <!-- Live Preview -->
    <div class="col-lg-5">
        <div class="card mb-4 sticky-top" style="top:1rem">
            <div class="card-header">
                <i class="bi bi-eye me-2"></i>Live Preview
            </div>
            <div class="card-body">
                <?= $this->Workflow->diagram($definition) ?>
            </div>
        </div>
    </div>

    <!-- Definition Editor -->
    <div class="col-lg-7">
        <!-- Workflow Info -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle me-2"></i>Workflow Properties</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Name</label>
                        <input type="text" class="form-control" value="<?= h($definition->getName()) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Label</label>
                        <input type="text" class="form-control" value="<?= h($definition->getLabel() ?? '') ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Table</label>
                        <input type="text" class="form-control" value="<?= h($definition->getTable()) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Field</label>
                        <input type="text" class="form-control" value="<?= h($definition->getField()) ?>" readonly>
                    </div>
                    <?php if ($definition->getDescription()) { ?>
                        <div class="col-12">
                            <label class="form-label text-muted small">Description</label>
                            <textarea class="form-control" rows="2" readonly><?= h($definition->getDescription()) ?></textarea>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- States -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-circle me-2"></i>States (<?= count($states) ?>)</span>
            </div>
            <div class="card-body p-0">
                <div class="accordion" id="statesAccordion">
                    <?php foreach ($states as $index => $state) { ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#state-<?= $index ?>">
                                    <span class="me-2" style="width:16px;height:16px;border-radius:4px;background:<?= h($state['color'] ?? '#6c757d') ?>;display:inline-block"></span>
                                    <code class="me-2"><?= h($state['name']) ?></code>
                                    <?php if ($state['isInitial']) { ?>
                                        <span class="badge bg-info me-1">Initial</span>
                                    <?php } ?>
                                    <?php if ($state['isFinal']) { ?>
                                        <span class="badge bg-dark me-1">Final</span>
                                    <?php } ?>
                                    <?php if ($state['isFailed']) { ?>
                                        <span class="badge bg-danger me-1">Failed</span>
                                    <?php } ?>
                                </button>
                            </h2>
                            <div id="state-<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#statesAccordion">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label text-muted small">Name</label>
                                            <input type="text" class="form-control form-control-sm" value="<?= h($state['name']) ?>" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-muted small">Label</label>
                                            <input type="text" class="form-control form-control-sm" value="<?= h($state['label'] ?? '') ?>" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-muted small">Color</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text" style="width:40px;background:<?= h($state['color'] ?? '#6c757d') ?>"></span>
                                                <input type="text" class="form-control" value="<?= h($state['color'] ?? '#6c757d') ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-muted small">Type</label>
                                            <input type="text" class="form-control form-control-sm" value="<?php
                                                $types = [];
                                                if ($state['isInitial']) {
                                                    $types[] = 'Initial';
                                                }
                                                if ($state['isFinal']) {
                                                    $types[] = 'Final';
                                                }
                                                if ($state['isFailed']) {
                                                    $types[] = 'Failed';
                                                }
                                                echo $types ? implode(', ', $types) : 'Normal';
                                            ?>" readonly>
                                        </div>
                                        <?php if ($state['flags']) { ?>
                                            <div class="col-12">
                                                <label class="form-label text-muted small">Flags</label>
                                                <div>
                                                    <?php foreach ($state['flags'] as $flag) { ?>
                                                        <span class="badge bg-purple me-1" style="background:#e9d5ff;color:#6b21a8"><?= h($flag) ?></span>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Transitions -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-arrow-right me-2"></i>Transitions (<?= count($transitions) ?>)</span>
            </div>
            <div class="card-body p-0">
                <div class="accordion" id="transitionsAccordion">
                    <?php foreach ($transitions as $index => $transition) { ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#transition-<?= $index ?>">
                                    <code class="me-2"><?= h($transition['name']) ?></code>
                                    <?php if ($transition['isHappy']) { ?>
                                        <i class="bi bi-star-fill text-success me-1" title="Happy path"></i>
                                    <?php } ?>
                                    <?php if ($transition['isAutomatic']) { ?>
                                        <i class="bi bi-lightning-fill text-warning me-1" title="Automatic"></i>
                                    <?php } ?>
                                    <span class="text-muted ms-2">
                                        <?= h(implode(', ', $transition['from'])) ?> &rarr; <?= h($transition['to']) ?>
                                    </span>
                                </button>
                            </h2>
                            <div id="transition-<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#transitionsAccordion">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label text-muted small">Name</label>
                                            <input type="text" class="form-control form-control-sm" value="<?= h($transition['name']) ?>" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-muted small">Options</label>
                                            <div>
                                                <?php if ($transition['isHappy']) { ?>
                                                    <span class="badge bg-success me-1">Happy Path</span>
                                                <?php } ?>
                                                <?php if ($transition['isAutomatic']) { ?>
                                                    <span class="badge bg-warning text-dark me-1">Automatic</span>
                                                <?php } ?>
                                                <?php if (!$transition['isHappy'] && !$transition['isAutomatic']) { ?>
                                                    <span class="text-muted">-</span>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-muted small">From States</label>
                                            <div>
                                                <?php foreach ($transition['from'] as $from) { ?>
                                                    <span class="badge bg-secondary me-1"><?= h($from) ?></span>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-muted small">To State</label>
                                            <div>
                                                <span class="badge bg-primary"><?= h($transition['to']) ?></span>
                                            </div>
                                        </div>
                                        <?php if ($transition['guards']) { ?>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small">Guards</label>
                                                <div>
                                                    <?php foreach ($transition['guards'] as $guard) { ?>
                                                        <code class="d-block small"><?= h($guard) ?></code>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        <?php } ?>
                                        <?php if ($transition['commands']) { ?>
                                            <div class="col-md-6">
                                                <label class="form-label text-muted small">Commands</label>
                                                <div>
                                                    <?php foreach ($transition['commands'] as $command) { ?>
                                                        <code class="d-block small"><?= h($command) ?></code>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
