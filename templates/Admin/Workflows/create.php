<?php
/**
 * @var \Cake\View\View $this
 * @var array{neon: bool, yaml: bool} $exportFormats
 */
$this->assign('title', 'New Workflow - Designer');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
            <i class="bi bi-plus-circle me-2"></i>New Workflow
        </h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><?= $this->Html->link('Admin', ['controller' => 'Workflow', 'action' => 'index']) ?></li>
                <li class="breadcrumb-item"><?= $this->Html->link('Workflows', ['action' => 'index']) ?></li>
                <li class="breadcrumb-item active">New Workflow</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="bi bi-upload me-1"></i>Import
        </button>
        <?php if ($exportFormats['neon'] || $exportFormats['yaml']) { ?>
            <button type="button" class="btn btn-primary" id="exportBtn">
                <i class="bi bi-download me-1"></i>Export & Download
            </button>
        <?php } else { ?>
            <span class="text-muted">Install nette/neon or symfony/yaml to export</span>
        <?php } ?>
    </div>
</div>

<?= $this->Form->create(null, ['id' => 'workflowDesignerForm']) ?>

<div class="row">
    <!-- Live Preview -->
    <div class="col-lg-5">
        <div class="card mb-4 sticky-top" style="top:1rem">
            <div class="card-header d-flex justify-content-between align-items-center">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="diagram-tab" data-bs-toggle="tab" data-bs-target="#diagram-preview" type="button" role="tab">
                            <i class="bi bi-diagram-3 me-1"></i>Diagram
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="config-tab" data-bs-toggle="tab" data-bs-target="#config-preview" type="button" role="tab">
                            <i class="bi bi-code me-1"></i>Config
                        </button>
                    </li>
                </ul>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshPreview">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="diagram-preview" role="tabpanel">
                        <div id="mermaidPreview" class="mermaid">
                            stateDiagram-v2
                            [*] --> pending
                            pending --> completed
                            completed --> [*]
                        </div>
                    </div>
                    <div class="tab-pane fade" id="config-preview" role="tabpanel">
                        <pre id="configPreview" class="bg-dark text-light p-3 rounded" style="max-height:400px;overflow:auto;font-size:0.85rem"><code></code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Definition Editor -->
    <div class="col-lg-7">
        <!-- Workflow Info -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Workflow Properties
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="workflowName" value="new_workflow" required pattern="[a-z_]+" title="Lowercase letters and underscores only">
                        <div class="form-text">Lowercase letters and underscores only</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Label</label>
                        <input type="text" class="form-control" name="label" id="workflowLabel" placeholder="Human-readable name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Table <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="table" id="workflowTable" value="Items" required>
                        <div class="form-text">CakePHP table alias (e.g., Orders, Users)</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Field <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="field" id="workflowField" value="state" required>
                        <div class="form-text">Database column for state</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Version</label>
                        <input type="text" class="form-control" name="version" id="workflowVersion" placeholder="1.0.0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Export Format</label>
                        <select class="form-select" name="export_format" id="exportFormat">
                            <?php if ($exportFormats['neon']) { ?>
                                <option value="neon">NEON</option>
                            <?php } ?>
                            <?php if ($exportFormats['yaml']) { ?>
                                <option value="yaml">YAML</option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="workflowDescription" rows="2" placeholder="Describe what this workflow does"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- States -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-circle me-2"></i>States</span>
                <button type="button" class="btn btn-sm btn-success" id="addStateBtn">
                    <i class="bi bi-plus-lg me-1"></i>Add State
                </button>
            </div>
            <div class="card-body p-0">
                <div id="statesContainer"></div>
                <div class="p-3 text-center text-muted" id="noStatesMsg">
                    <i class="bi bi-info-circle me-2"></i>No states defined. Click "Add State" to begin.
                </div>
            </div>
        </div>

        <!-- Transitions -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-arrow-right me-2"></i>Transitions</span>
                <button type="button" class="btn btn-sm btn-success" id="addTransitionBtn">
                    <i class="bi bi-plus-lg me-1"></i>Add Transition
                </button>
            </div>
            <div class="card-body p-0">
                <div id="transitionsContainer"></div>
                <div class="p-3 text-center text-muted" id="noTransitionsMsg">
                    <i class="bi bi-info-circle me-2"></i>No transitions defined. Add states first, then create transitions.
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->Form->end() ?>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Import Workflow</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Paste your NEON or YAML workflow configuration below. This will replace the current designer content.
                </div>
                <div class="mb-3">
                    <label class="form-label">Configuration (NEON/YAML)</label>
                    <textarea class="form-control font-monospace" id="importConfig" rows="15" placeholder="workflow_name:
    table: TableName
    field: state
    states:
        pending:
            initial: true
        completed:
            final: true
    transitions:
        complete:
            from: [pending]
            to: completed"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="doImportBtn">
                    <i class="bi bi-check-lg me-1"></i>Import
                </button>
            </div>
        </div>
    </div>
</div>

<!-- State Template -->
<template id="stateTemplate">
    <div class="state-item border-bottom p-3" data-state-index="__INDEX__">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="d-flex align-items-center gap-2">
                <span class="state-color-indicator" style="width:16px;height:16px;border-radius:4px;background:#6c757d;display:inline-block"></span>
                <strong class="state-display-name">New State</strong>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger remove-state-btn">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label small text-muted">Name</label>
                <input type="text" class="form-control form-control-sm state-name" name="states[__INDEX__][name]" placeholder="state_name" required pattern="[a-z_]+">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Label</label>
                <input type="text" class="form-control form-control-sm state-label" name="states[__INDEX__][label]" placeholder="Display Name">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Color</label>
                <div class="input-group input-group-sm">
                    <input type="color" class="form-control form-control-color state-color-picker" name="states[__INDEX__][color]" value="#6c757d" style="width:40px;padding:2px">
                    <input type="text" class="form-control state-color-text" value="#6c757d" placeholder="#6c757d">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted">Type</label>
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input state-initial" name="states[__INDEX__][initial]" value="1" id="state__INDEX__initial">
                        <label class="form-check-label small" for="state__INDEX__initial">Initial</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input state-final" name="states[__INDEX__][final]" value="1" id="state__INDEX__final">
                        <label class="form-check-label small" for="state__INDEX__final">Final</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input state-failed" name="states[__INDEX__][failed]" value="1" id="state__INDEX__failed">
                        <label class="form-check-label small" for="state__INDEX__failed">Failed</label>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted">Flags (comma-separated)</label>
                <input type="text" class="form-control form-control-sm state-flags" name="states[__INDEX__][flags]" placeholder="reserved, billable">
            </div>
        </div>
    </div>
</template>

<!-- Transition Template -->
<template id="transitionTemplate">
    <div class="transition-item border-bottom p-3" data-transition-index="__INDEX__">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="d-flex align-items-center gap-2">
                <strong class="transition-display-name">New Transition</strong>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger remove-transition-btn">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label small text-muted">Name</label>
                <input type="text" class="form-control form-control-sm transition-name" name="transitions[__INDEX__][name]" placeholder="action_name" required pattern="[a-z_]+">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">From States (comma-sep)</label>
                <input type="text" class="form-control form-control-sm transition-from" name="transitions[__INDEX__][from]" placeholder="pending, draft">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">To State</label>
                <input type="text" class="form-control form-control-sm transition-to" name="transitions[__INDEX__][to]" placeholder="completed">
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted">Options</label>
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input transition-happy" name="transitions[__INDEX__][happy]" value="1" id="transition__INDEX__happy">
                        <label class="form-check-label small" for="transition__INDEX__happy">
                            <i class="bi bi-star-fill text-success"></i> Happy Path
                        </label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input transition-automatic" name="transitions[__INDEX__][automatic]" value="1" id="transition__INDEX__automatic">
                        <label class="form-check-label small" for="transition__INDEX__automatic">
                            <i class="bi bi-lightning-fill text-warning"></i> Automatic
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Guard</label>
                <input type="text" class="form-control form-control-sm transition-guard" name="transitions[__INDEX__][guard]" placeholder="ClassName::method">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Command</label>
                <input type="text" class="form-control form-control-sm transition-command" name="transitions[__INDEX__][command]" placeholder="ClassName::method">
            </div>
        </div>
    </div>
</template>

<?php $this->append('script'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const statesContainer = document.getElementById('statesContainer');
    const transitionsContainer = document.getElementById('transitionsContainer');
    const stateTemplate = document.getElementById('stateTemplate');
    const transitionTemplate = document.getElementById('transitionTemplate');
    const noStatesMsg = document.getElementById('noStatesMsg');
    const noTransitionsMsg = document.getElementById('noTransitionsMsg');
    const form = document.getElementById('workflowDesignerForm');

    let stateIndex = 0;
    let transitionIndex = 0;
    let skipPreviewUpdate = true; // Skip updates during initial setup
    let mermaidRenderCount = 0;

    // Add default states on load (without triggering preview updates)
    addState('pending', 'Pending', '#ffc107', true, false, false);
    addState('completed', 'Completed', '#28a745', false, true, false);
    addTransition('complete', 'pending', 'completed', true, false);

    // Now enable preview updates and render once
    skipPreviewUpdate = false;
    updateMermaidPreview();

    document.getElementById('addStateBtn').addEventListener('click', function() { addState(); });
    document.getElementById('addTransitionBtn').addEventListener('click', function() { addTransition(); });
    document.getElementById('exportBtn')?.addEventListener('click', function() { form.submit(); });
    document.getElementById('refreshPreview').addEventListener('click', function() {
        updateMermaidPreview();
    });

    // Re-render when switching to diagram tab (in case it wasn't visible during update)
    document.getElementById('diagram-tab').addEventListener('shown.bs.tab', function() {
        updateMermaidPreview();
    });

    // Import functionality
    document.getElementById('doImportBtn').addEventListener('click', function() {
        const config = document.getElementById('importConfig').value.trim();
        if (!config) {
            alert('Please paste a configuration to import.');
            return;
        }

        try {
            const parsed = parseSimpleYaml(config);
            if (!parsed) {
                alert('Could not parse configuration. Please check the format.');
                return;
            }

            // Clear existing states and transitions
            statesContainer.innerHTML = '';
            transitionsContainer.innerHTML = '';
            stateIndex = 0;
            transitionIndex = 0;
            skipPreviewUpdate = true;

            // Set workflow properties
            const workflowName = Object.keys(parsed)[0];
            const workflow = parsed[workflowName];

            document.getElementById('workflowName').value = workflowName || '';
            document.getElementById('workflowTable').value = workflow.table || '';
            document.getElementById('workflowField').value = workflow.field || 'state';
            document.getElementById('workflowLabel').value = workflow.label || '';
            document.getElementById('workflowVersion').value = workflow.version || '';
            document.getElementById('workflowDescription').value = workflow.description || '';

            // Add states
            if (workflow.states) {
                for (const [stateName, stateConfig] of Object.entries(workflow.states)) {
                    const cfg = stateConfig || {};
                    addState(
                        stateName,
                        cfg.label || '',
                        cfg.color || '#6c757d',
                        !!cfg.initial,
                        !!cfg.final,
                        !!cfg.failed,
                        Array.isArray(cfg.flags) ? cfg.flags.join(', ') : (cfg.flags || '')
                    );
                }
            }

            // Add transitions
            if (workflow.transitions) {
                for (const [transName, transConfig] of Object.entries(workflow.transitions)) {
                    const cfg = transConfig || {};
                    const fromStates = Array.isArray(cfg.from) ? cfg.from.join(', ') : (cfg.from || '');
                    const guards = Array.isArray(cfg.guards) ? cfg.guards.join(', ') : (cfg.guards || cfg.guard || '');
                    const commands = Array.isArray(cfg.commands) ? cfg.commands.join(', ') : (cfg.commands || cfg.command || '');
                    addTransition(
                        transName,
                        fromStates,
                        cfg.to || '',
                        !!cfg.happy,
                        !!cfg.automatic,
                        guards,
                        commands
                    );
                }
            }

            // Update visibility messages
            noStatesMsg.style.display = statesContainer.children.length === 0 ? 'block' : 'none';
            noTransitionsMsg.style.display = transitionsContainer.children.length === 0 ? 'block' : 'none';

            // Enable preview and update
            skipPreviewUpdate = false;
            updateMermaidPreview();

            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('importModal')).hide();

        } catch (e) {
            alert('Error parsing configuration: ' + e.message);
        }
    });

    // Simple YAML/NEON parser (handles basic indented key-value structure)
    function parseSimpleYaml(text) {
        const result = {};
        const lines = text.split('\n');
        const stack = [{ obj: result, indent: -1 }];

        for (let line of lines) {
            // Skip empty lines and comments
            if (!line.trim() || line.trim().startsWith('#')) continue;

            const indent = line.search(/\S/);
            const content = line.trim();

            // Pop stack to find parent at correct indent level
            while (stack.length > 1 && stack[stack.length - 1].indent >= indent) {
                stack.pop();
            }
            const parent = stack[stack.length - 1].obj;

            // Parse key: value
            const colonIdx = content.indexOf(':');
            if (colonIdx === -1) continue;

            const key = content.substring(0, colonIdx).trim();
            let value = content.substring(colonIdx + 1).trim();

            if (value === '' || value === '|' || value === '>') {
                // Object or multiline - create nested object
                parent[key] = {};
                stack.push({ obj: parent[key], indent: indent });
            } else if (value.startsWith('[') && value.endsWith(']')) {
                // Inline array
                const inner = value.slice(1, -1);
                parent[key] = inner ? inner.split(',').map(s => s.trim().replace(/^['"]|['"]$/g, '')) : [];
            } else if (value === 'true') {
                parent[key] = true;
            } else if (value === 'false') {
                parent[key] = false;
            } else {
                // Remove quotes if present
                parent[key] = value.replace(/^['"]|['"]$/g, '');
            }
        }

        return result;
    }

    function addState(name = '', label = '', color = '#6c757d', isInitial = false, isFinal = false, isFailed = false, flags = '') {
        const html = stateTemplate.innerHTML.replace(/__INDEX__/g, stateIndex);
        const div = document.createElement('div');
        div.innerHTML = html;
        const stateItem = div.firstElementChild;
        statesContainer.appendChild(stateItem);
        noStatesMsg.style.display = 'none';

        if (name) {
            stateItem.querySelector('.state-name').value = name;
            stateItem.querySelector('.state-display-name').textContent = name;
        }
        if (label) stateItem.querySelector('.state-label').value = label;
        if (color) {
            stateItem.querySelector('.state-color-picker').value = color;
            stateItem.querySelector('.state-color-text').value = color;
            stateItem.querySelector('.state-color-indicator').style.background = color;
        }
        if (isInitial) stateItem.querySelector('.state-initial').checked = true;
        if (isFinal) stateItem.querySelector('.state-final').checked = true;
        if (isFailed) stateItem.querySelector('.state-failed').checked = true;
        if (flags) stateItem.querySelector('.state-flags').value = flags;

        stateItem.querySelector('.state-name').addEventListener('input', function() {
            stateItem.querySelector('.state-display-name').textContent = this.value || 'New State';
            updateMermaidPreview();
        });
        stateItem.querySelector('.state-color-picker').addEventListener('input', function() {
            stateItem.querySelector('.state-color-text').value = this.value;
            stateItem.querySelector('.state-color-indicator').style.background = this.value;
        });
        stateItem.querySelector('.state-color-text').addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                stateItem.querySelector('.state-color-picker').value = this.value;
                stateItem.querySelector('.state-color-indicator').style.background = this.value;
            }
        });
        stateItem.querySelector('.remove-state-btn').addEventListener('click', function() {
            stateItem.remove();
            if (statesContainer.children.length === 0) noStatesMsg.style.display = 'block';
            updateMermaidPreview();
        });
        stateItem.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.addEventListener('change', updateMermaidPreview));
        stateIndex++;
        updateMermaidPreview();
    }

    function addTransition(name = '', from = '', to = '', isHappy = false, isAutomatic = false, guard = '', command = '') {
        const html = transitionTemplate.innerHTML.replace(/__INDEX__/g, transitionIndex);
        const div = document.createElement('div');
        div.innerHTML = html;
        const transitionItem = div.firstElementChild;
        transitionsContainer.appendChild(transitionItem);
        noTransitionsMsg.style.display = 'none';

        if (name) {
            transitionItem.querySelector('.transition-name').value = name;
            transitionItem.querySelector('.transition-display-name').textContent = name;
        }
        if (from) transitionItem.querySelector('.transition-from').value = from;
        if (to) transitionItem.querySelector('.transition-to').value = to;
        if (isHappy) transitionItem.querySelector('.transition-happy').checked = true;
        if (isAutomatic) transitionItem.querySelector('.transition-automatic').checked = true;
        if (guard) transitionItem.querySelector('.transition-guard').value = guard;
        if (command) transitionItem.querySelector('.transition-command').value = command;

        transitionItem.querySelector('.transition-name').addEventListener('input', function() {
            transitionItem.querySelector('.transition-display-name').textContent = this.value || 'New Transition';
            updateMermaidPreview();
        });
        transitionItem.querySelector('.transition-from').addEventListener('input', updateMermaidPreview);
        transitionItem.querySelector('.transition-to').addEventListener('input', updateMermaidPreview);
        transitionItem.querySelector('.remove-transition-btn').addEventListener('click', function() {
            transitionItem.remove();
            if (transitionsContainer.children.length === 0) noTransitionsMsg.style.display = 'block';
            updateMermaidPreview();
        });
        transitionItem.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.addEventListener('change', updateMermaidPreview));
        transitionIndex++;
        updateMermaidPreview();
    }

    function updateMermaidPreview() {
        if (skipPreviewUpdate) return;

        const statesData = [];
        const transitionsData = [];

        statesContainer.querySelectorAll('.state-item').forEach(item => {
            const name = item.querySelector('.state-name').value;
            if (name) {
                statesData.push({
                    name: name,
                    label: item.querySelector('.state-label').value,
                    color: item.querySelector('.state-color-picker').value,
                    isInitial: item.querySelector('.state-initial').checked,
                    isFinal: item.querySelector('.state-final').checked,
                    isFailed: item.querySelector('.state-failed').checked,
                    flags: item.querySelector('.state-flags').value
                });
            }
        });

        transitionsContainer.querySelectorAll('.transition-item').forEach(item => {
            const name = item.querySelector('.transition-name').value;
            const from = item.querySelector('.transition-from').value;
            const to = item.querySelector('.transition-to').value;
            const isHappy = item.querySelector('.transition-happy').checked;
            const isAutomatic = item.querySelector('.transition-automatic').checked;
            const guard = item.querySelector('.transition-guard').value;
            const command = item.querySelector('.transition-command').value;

            if (name && from && to) {
                transitionsData.push({
                    name: name,
                    from: from.split(',').map(s => s.trim()).filter(s => s),
                    to: to.trim(),
                    isHappy: isHappy,
                    isAutomatic: isAutomatic,
                    guard: guard,
                    command: command
                });
            }
        });

        // Update Mermaid diagram
        let diagram = 'stateDiagram-v2\n';
        statesData.filter(s => s.isInitial).forEach(s => { diagram += `    [*] --> ${s.name}\n`; });
        transitionsData.forEach(t => {
            t.from.forEach(fromState => {
                const label = t.isHappy ? `${t.name} ⭐` : t.name;
                diagram += `    ${fromState} --> ${t.to}: ${label}\n`;
            });
        });
        statesData.filter(s => s.isFinal).forEach(s => { diagram += `    ${s.name} --> [*]\n`; });

        if (statesData.length === 0) diagram = 'stateDiagram-v2\n    [*] --> new_state\n    new_state --> [*]';

        renderMermaidDiagram(diagram);

        // Update NEON config preview
        updateConfigPreview(statesData, transitionsData);
    }

    function renderMermaidDiagram(diagram) {
        const container = document.getElementById('diagram-preview');
        const graphId = 'mermaid-graph-' + (++mermaidRenderCount);

        if (typeof mermaid !== 'undefined' && mermaid.render) {
            // Use mermaid.render() for proper re-rendering
            mermaid.render(graphId, diagram).then(function(result) {
                container.innerHTML = '<div id="mermaidPreview">' + result.svg + '</div>';
            }).catch(function(err) {
                container.innerHTML = '<div id="mermaidPreview" class="text-danger">Diagram error: ' + err.message + '</div>';
            });
        } else if (typeof mermaid !== 'undefined') {
            // Fallback: recreate the element
            container.innerHTML = '<div id="mermaidPreview" class="mermaid">' + diagram + '</div>';
            mermaid.init(undefined, container.querySelector('.mermaid'));
        }
    }

    function updateConfigPreview(statesData, transitionsData) {
        const workflowName = document.getElementById('workflowName').value || 'new_workflow';
        const table = document.getElementById('workflowTable').value || 'Items';
        const field = document.getElementById('workflowField').value || 'state';
        const label = document.getElementById('workflowLabel').value;
        const version = document.getElementById('workflowVersion').value;
        const description = document.getElementById('workflowDescription').value;

        let neon = `${workflowName}:\n`;
        neon += `    table: ${table}\n`;
        neon += `    field: ${field}\n`;
        if (label) neon += `    label: ${label}\n`;
        if (version) neon += `    version: "${version}"\n`;
        if (description) neon += `    description: "${description}"\n`;

        // States
        if (statesData.length > 0) {
            neon += `    states:\n`;
            statesData.forEach(state => {
                neon += `        ${state.name}:\n`;
                if (state.label) neon += `            label: ${state.label}\n`;
                if (state.color && state.color !== '#6c757d') neon += `            color: '${state.color}'\n`;
                if (state.isInitial) neon += `            initial: true\n`;
                if (state.isFinal) neon += `            final: true\n`;
                if (state.isFailed) neon += `            failed: true\n`;
                if (state.flags) {
                    const flagsArr = state.flags.split(',').map(f => f.trim()).filter(f => f);
                    if (flagsArr.length > 0) {
                        neon += `            flags: [${flagsArr.join(', ')}]\n`;
                    }
                }
            });
        }

        // Transitions
        if (transitionsData.length > 0) {
            neon += `    transitions:\n`;
            transitionsData.forEach(t => {
                neon += `        ${t.name}:\n`;
                neon += `            from: [${t.from.join(', ')}]\n`;
                neon += `            to: ${t.to}\n`;
                if (t.isHappy) neon += `            happy: true\n`;
                if (t.isAutomatic) neon += `            automatic: true\n`;
                if (t.guard) neon += `            guards: [${t.guard}]\n`;
                if (t.command) neon += `            commands: [${t.command}]\n`;
            });
        }

        document.querySelector('#configPreview code').textContent = neon;
    }

    // Also update config when workflow properties change
    ['workflowName', 'workflowTable', 'workflowField', 'workflowLabel', 'workflowVersion', 'workflowDescription'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', updateMermaidPreview);
    });
});
</script>
<?php $this->end(); ?>
