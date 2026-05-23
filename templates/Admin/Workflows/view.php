<?php
/**
 * @var \Cake\View\View $this
 * @var \Workflow\Engine\Definition\Definition $definition
 * @var array<string, int> $stateCounts
 * @var int $totalActive
 * @var array<\Workflow\Model\Entity\WorkflowTransition> $recentTransitions
 * @var int $transitionsToday
 * @var array<\Workflow\Model\Entity\WorkflowTimeout> $pendingTimeouts
 * @var array{neon: bool, yaml: bool} $exportFormats
 * @var array<array{type: string, severity: string, message: string, context: array<string, mixed>}> $issues
 * @var array{errors: array<array{type: string, severity: string, message: string, context: array<string, mixed>}>, warnings: array<array{type: string, severity: string, message: string, context: array<string, mixed>}>, info: array<array{type: string, severity: string, message: string, context: array<string, mixed>}>} $issuesBySeverity
 */
$this->assign('title', $definition->getName());

$stateCount = count($definition->getStates());
$transitionCount = count($definition->getTransitions());
$hasExport = $exportFormats['neon'] || $exportFormats['yaml'];
$exportFormat = $exportFormats['neon'] ? 'neon' : 'yaml';
$exportLabel = $exportFormats['neon'] ? 'NEON' : 'YAML';
$errorCount = count($issuesBySeverity['errors']);
$warningCount = count($issuesBySeverity['warnings']);
$terminalStateIssues = array_filter(
    $issues,
    static fn (array $issue): bool => $issue['type'] === 'terminal_state_outgoing_transition',
);
$terminalStateIssueMap = [];
foreach ($terminalStateIssues as $issue) {
    $transitionName = $issue['context']['transition'] ?? null;
    if (is_string($transitionName)) {
        $terminalStateIssueMap[$transitionName] = $issue['message'];
    }
}
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
    <div>
        <?php if ($hasExport) { ?>
            <?= $this->Html->link(
                '<i class="bi bi-download me-1"></i>Export ' . $exportLabel,
                ['action' => 'export', $definition->getName(), $exportFormat],
                ['class' => 'btn btn-outline-secondary me-2', 'escapeTitle' => false],
            ) ?>
        <?php } ?>
        <button class="btn btn-outline-secondary me-2" id="export-svg">
            <i class="bi bi-image me-1"></i>Export SVG
        </button>
        <?= $this->Html->link(
            '<i class="bi bi-grid-3x3 me-1"></i>Matrix',
            ['action' => 'matrix', $definition->getName()],
            ['class' => 'btn btn-primary', 'escapeTitle' => false],
        ) ?>
    </div>
</div>

<?php if ($errorCount > 0 || $warningCount > 0) { ?>
    <div class="alert <?= $errorCount > 0 ? 'alert-danger' : 'alert-warning' ?> d-flex justify-content-between align-items-center mb-4">
        <div>
            <strong>Validation issues detected.</strong>
            <?= $errorCount ?> error(s), <?= $warningCount ?> warning(s).
            <?php if ($terminalStateIssues) { ?>
                Terminal-state contradictions are highlighted below.
            <?php } ?>
        </div>
        <div>
            <?= $this->Html->link(
                'Open Validation',
                ['action' => 'validate', $definition->getName()],
                ['class' => 'btn btn-sm ' . ($errorCount > 0 ? 'btn-outline-danger' : 'btn-outline-warning')],
            ) ?>
        </div>
    </div>
<?php } ?>

<!-- Section Navigation -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link active" href="#diagram">Diagram</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#states-section">States (<?= $stateCount ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#transitions-section">Transitions (<?= $transitionCount ?>)</a>
    </li>
    <li class="nav-item">
        <?= $this->Html->link(
            'History',
            ['controller' => 'Transitions', 'action' => 'index', '?' => ['workflow' => $definition->getName()]],
            ['class' => 'nav-link'],
        ) ?>
    </li>
</ul>

<div class="row">
    <!-- Diagram -->
    <div class="col-lg-8">
        <div class="diagram-container mb-4" id="diagram">
            <div class="d-flex gap-2 mb-3">
                <div class="btn-group btn-group-sm" id="view-toggle">
                    <button class="btn btn-outline-secondary active" id="btn-diagram">Diagram</button>
                    <button class="btn btn-outline-secondary" id="btn-code">Code</button>
                </div>
                <div class="btn-group btn-group-sm ms-auto" id="zoom-controls">
                    <button class="btn btn-outline-secondary" id="zoom-in" title="Zoom In"><i class="bi bi-zoom-in"></i></button>
                    <button class="btn btn-outline-secondary" id="zoom-out" title="Zoom Out"><i class="bi bi-zoom-out"></i></button>
                    <button class="btn btn-outline-secondary" id="fullscreen" title="Fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
                </div>
            </div>
            <div id="diagram-view">
                <?= $this->Workflow->diagram($definition) ?>
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        <span class="me-3"><span style="color:#2e7d32">━━</span> Happy path</span>
                        <span class="me-3"><span style="color:#666">━━</span> Normal</span>
                        <span><span style="color:#ff9800">┄┄</span> Automatic</span>
                    </small>
                </div>
            </div>
            <div id="code-view" style="display:none">
                <pre class="bg-dark text-light p-3 rounded" style="overflow-x:auto;font-size:0.85rem"><code><?= h($this->Workflow->getMermaidCode($definition)) ?></code></pre>
                <div class="mt-2">
                    <button class="btn btn-sm btn-outline-secondary" id="copy-code" title="Copy to clipboard">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
            </div>
        </div>

        <!-- States Table -->
        <div class="card mb-4" id="states-section">
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
                                        <?= $count ?>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transitions Table -->
        <div class="card" id="transitions-section">
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
                            <th>Condition</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($definition->getTransitions() as $transition) { ?>
                            <?php $transitionIssue = $terminalStateIssueMap[$transition->getName()] ?? null; ?>
                            <tr<?= $transitionIssue ? ' class="table-danger"' : '' ?>>
                                <td>
                                    <code><?= h($transition->getName()) ?></code>
                                    <?php if ($transition->isHappy()) { ?>
                                        <i class="bi bi-star-fill happy-path" title="Happy path"></i>
                                    <?php } ?>
                                    <?php if ($transition->isAutomatic()) { ?>
                                        <i class="bi bi-lightning-fill text-warning" title="Automatic transition"></i>
                                    <?php } ?>
                                    <?php if ($transitionIssue) { ?>
                                        <i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="<?= h($transitionIssue) ?>"></i>
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
                                <td>
                                    <?php if ($transition->getCondition()) { ?>
                                        <code class="small text-info"><?= h($transition->getCondition()) ?></code>
                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php if ($transitionIssue) { ?>
                                <tr class="table-danger">
                                    <td colspan="7" class="small text-danger">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i><?= h($transitionIssue) ?>
                                    </td>
                                </tr>
                            <?php } ?>
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
                        <td>
                            <span class="badge bg-primary">v<?= $definition->getVersion() ?></span>
                            <small class="text-muted ms-1">(<?= h($definition->getVersionHash()) ?>)</small>
                        </td>
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
                                <strong><?= h($timeout->model) ?> #<?= h($timeout->foreign_key) ?>: <?= h($timeout->transition_name) ?></strong>
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
                            <?php
                            $guards = $t->getGuardsEvaluated();
                            $commands = $t->getCommandsExecuted();
                            $usedLock = $t->usedLock();
                            ?>
                            <li class="mb-2">
                                <small class="text-muted"><?= $t->created->diffForHumans() ?></small><br>
                                <strong><?= h($t->model) ?> #<?= h($t->foreign_key) ?></strong>:
                                <?= h($t->from_state) ?> &rarr; <?= h($t->to_state) ?>
                                <?php if ($guards || $commands || $usedLock) { ?>
                                    <div class="small text-muted mt-1">
                                        <?php if ($guards) { ?>
                                            <span title="Guards: <?= h(implode(', ', $guards)) ?>"><i class="bi bi-shield-check"></i> <?= count($guards) ?></span>
                                        <?php } ?>
                                        <?php if ($commands) { ?>
                                            <span class="ms-2" title="Commands: <?= h(implode(', ', $commands)) ?>"><i class="bi bi-gear"></i> <?= count($commands) ?></span>
                                        <?php } ?>
                                        <?php if ($usedLock) { ?>
                                            <span class="ms-2" title="Used lock"><i class="bi bi-lock"></i></span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
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
                        '<i class="bi bi-pencil-square me-2"></i>Designer',
                        ['action' => 'designer', $definition->getName()],
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
                    <?= $this->Html->link(
                        '<i class="bi bi-lock me-2"></i>View Locks',
                        ['controller' => 'Locks', 'action' => 'index', '?' => ['workflow' => $definition->getName()]],
                        ['class' => 'btn btn-outline-info', 'escapeTitle' => false],
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-exclamation-triangle me-2"></i>Check Orphans',
                        ['controller' => 'Orphans', 'action' => 'index', '?' => ['workflow' => $definition->getName()]],
                        ['class' => 'btn btn-outline-danger', 'escapeTitle' => false],
                    ) ?>
                    <?= $this->Html->link(
                        '<i class="bi bi-check2-circle me-2"></i>Validate',
                        ['action' => 'validate', $definition->getName()],
                        ['class' => 'btn btn-outline-success', 'escapeTitle' => false],
                    ) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let scale = 1;
    const diagramContainer = document.getElementById('diagram');
    const diagramView = document.getElementById('diagram-view');
    const codeView = document.getElementById('code-view');
    const btnDiagram = document.getElementById('btn-diagram');
    const btnCode = document.getElementById('btn-code');
    const zoomControls = document.getElementById('zoom-controls');
    const mermaidDiv = diagramContainer?.querySelector('.mermaid');

    // Make mermaid container scrollable when zoomed
    if (mermaidDiv) {
        mermaidDiv.style.overflow = 'auto';
        mermaidDiv.style.maxHeight = '500px';
    }

    function getSvg() {
        return diagramContainer?.querySelector('svg');
    }

    function updateZoom() {
        const svg = getSvg();
        if (svg) {
            svg.style.transform = 'scale(' + scale + ')';
            svg.style.transformOrigin = 'top left';
        }
    }

    // Diagram/Code toggle
    btnDiagram?.addEventListener('click', function() {
        diagramView.style.display = 'block';
        codeView.style.display = 'none';
        zoomControls.style.display = '';
        btnDiagram.classList.add('active');
        btnCode.classList.remove('active');
    });

    btnCode?.addEventListener('click', function() {
        diagramView.style.display = 'none';
        codeView.style.display = 'block';
        zoomControls.style.display = 'none';
        btnCode.classList.add('active');
        btnDiagram.classList.remove('active');
    });

    // Copy code to clipboard
    document.getElementById('copy-code')?.addEventListener('click', function() {
        const code = codeView?.querySelector('code')?.textContent;
        if (code) {
            navigator.clipboard.writeText(code).then(function() {
                const btn = document.getElementById('copy-code');
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
                setTimeout(function() {
                    btn.innerHTML = originalHtml;
                }, 2000);
            });
        }
    });

    document.getElementById('zoom-in')?.addEventListener('click', function() {
        scale = Math.min(scale + 0.2, 3);
        updateZoom();
    });

    document.getElementById('zoom-out')?.addEventListener('click', function() {
        scale = Math.max(scale - 0.2, 0.5);
        updateZoom();
    });

    document.getElementById('fullscreen')?.addEventListener('click', function() {
        if (diagramContainer) {
            if (document.fullscreenElement) {
                document.exitFullscreen();
                if (mermaidDiv) mermaidDiv.style.maxHeight = '500px';
            } else {
                diagramContainer.requestFullscreen();
                if (mermaidDiv) mermaidDiv.style.maxHeight = '100vh';
            }
        }
    });

    // Export SVG
    document.getElementById('export-svg')?.addEventListener('click', function() {
        const svg = getSvg();
        if (!svg) {
            alert('No diagram to export');
            return;
        }

        // Clone SVG and prepare for export
        const svgClone = svg.cloneNode(true);
        svgClone.style.transform = '';

        // Get SVG dimensions
        const bbox = svg.getBBox();
        const width = bbox.width + 40;
        const height = bbox.height + 40;
        svgClone.setAttribute('width', width);
        svgClone.setAttribute('height', height);
        svgClone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');

        // Add white background rect
        const bgRect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        bgRect.setAttribute('width', '100%');
        bgRect.setAttribute('height', '100%');
        bgRect.setAttribute('fill', '#ffffff');
        svgClone.insertBefore(bgRect, svgClone.firstChild);

        // Serialize and download SVG
        const serializer = new XMLSerializer();
        const svgString = '<?xml version="1.0" encoding="UTF-8"?>\n' + serializer.serializeToString(svgClone);
        const svgBlob = new Blob([svgString], {type: 'image/svg+xml;charset=utf-8'});

        const link = document.createElement('a');
        link.download = '<?= h($definition->getName()) ?>-workflow.svg';
        link.href = URL.createObjectURL(svgBlob);
        link.click();
        URL.revokeObjectURL(link.href);
    });
})();
</script>
