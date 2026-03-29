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
    <div>
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
                <span><i class="bi bi-eye me-2"></i>Live Preview</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshPreview">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <div class="card-body">
                <div id="mermaidPreview" class="mermaid">
                    stateDiagram-v2
                    [*] --> pending
                    pending --> completed
                    completed --> [*]
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
                <div id="statesContainer">
                    <!-- State templates will be added here -->
                </div>
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
                <div id="transitionsContainer">
                    <!-- Transition templates will be added here -->
                </div>
                <div class="p-3 text-center text-muted" id="noTransitionsMsg">
                    <i class="bi bi-info-circle me-2"></i>No transitions defined. Add states first, then create transitions.
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->Form->end() ?>

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
                <span class="transition-badges"></span>
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

    // Add default states on load
    addState('pending', 'Pending', '#ffc107', true, false, false);
    addState('completed', 'Completed', '#28a745', false, true, false);

    // Add default transition
    addTransition('complete', 'pending', 'completed', true, false);

    // Event listeners
    document.getElementById('addStateBtn').addEventListener('click', function() {
        addState();
    });

    document.getElementById('addTransitionBtn').addEventListener('click', function() {
        addTransition();
    });

    document.getElementById('exportBtn')?.addEventListener('click', function() {
        form.submit();
    });

    document.getElementById('refreshPreview').addEventListener('click', updateMermaidPreview);

    // Add state function
    function addState(name = '', label = '', color = '#6c757d', isInitial = false, isFinal = false, isFailed = false) {
        const html = stateTemplate.innerHTML
            .replace(/__INDEX__/g, stateIndex);

        const div = document.createElement('div');
        div.innerHTML = html;
        const stateItem = div.firstElementChild;

        statesContainer.appendChild(stateItem);
        noStatesMsg.style.display = 'none';

        // Set values
        if (name) {
            stateItem.querySelector('.state-name').value = name;
            stateItem.querySelector('.state-display-name').textContent = name;
        }
        if (label) {
            stateItem.querySelector('.state-label').value = label;
        }
        if (color) {
            stateItem.querySelector('.state-color-picker').value = color;
            stateItem.querySelector('.state-color-text').value = color;
            stateItem.querySelector('.state-color-indicator').style.background = color;
        }
        if (isInitial) {
            stateItem.querySelector('.state-initial').checked = true;
        }
        if (isFinal) {
            stateItem.querySelector('.state-final').checked = true;
        }
        if (isFailed) {
            stateItem.querySelector('.state-failed').checked = true;
        }

        // Event listeners for this state
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
            if (statesContainer.children.length === 0) {
                noStatesMsg.style.display = 'block';
            }
            updateMermaidPreview();
        });

        stateItem.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', updateMermaidPreview);
        });

        stateIndex++;
        updateMermaidPreview();
    }

    // Add transition function
    function addTransition(name = '', from = '', to = '', isHappy = false, isAutomatic = false) {
        const html = transitionTemplate.innerHTML
            .replace(/__INDEX__/g, transitionIndex);

        const div = document.createElement('div');
        div.innerHTML = html;
        const transitionItem = div.firstElementChild;

        transitionsContainer.appendChild(transitionItem);
        noTransitionsMsg.style.display = 'none';

        // Set values
        if (name) {
            transitionItem.querySelector('.transition-name').value = name;
            transitionItem.querySelector('.transition-display-name').textContent = name;
        }
        if (from) {
            transitionItem.querySelector('.transition-from').value = from;
        }
        if (to) {
            transitionItem.querySelector('.transition-to').value = to;
        }
        if (isHappy) {
            transitionItem.querySelector('.transition-happy').checked = true;
        }
        if (isAutomatic) {
            transitionItem.querySelector('.transition-automatic').checked = true;
        }

        // Event listeners
        transitionItem.querySelector('.transition-name').addEventListener('input', function() {
            transitionItem.querySelector('.transition-display-name').textContent = this.value || 'New Transition';
            updateMermaidPreview();
        });

        transitionItem.querySelector('.transition-from').addEventListener('input', updateMermaidPreview);
        transitionItem.querySelector('.transition-to').addEventListener('input', updateMermaidPreview);

        transitionItem.querySelector('.remove-transition-btn').addEventListener('click', function() {
            transitionItem.remove();
            if (transitionsContainer.children.length === 0) {
                noTransitionsMsg.style.display = 'block';
            }
            updateMermaidPreview();
        });

        transitionItem.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', updateMermaidPreview);
        });

        transitionIndex++;
        updateMermaidPreview();
    }

    // Update Mermaid diagram
    function updateMermaidPreview() {
        const states = [];
        const transitions = [];

        // Collect states
        statesContainer.querySelectorAll('.state-item').forEach(item => {
            const name = item.querySelector('.state-name').value;
            if (name) {
                states.push({
                    name: name,
                    isInitial: item.querySelector('.state-initial').checked,
                    isFinal: item.querySelector('.state-final').checked
                });
            }
        });

        // Collect transitions
        transitionsContainer.querySelectorAll('.transition-item').forEach(item => {
            const name = item.querySelector('.transition-name').value;
            const from = item.querySelector('.transition-from').value;
            const to = item.querySelector('.transition-to').value;
            const isHappy = item.querySelector('.transition-happy').checked;

            if (name && from && to) {
                const fromStates = from.split(',').map(s => s.trim()).filter(s => s);
                fromStates.forEach(fromState => {
                    transitions.push({
                        from: fromState,
                        to: to,
                        name: name,
                        isHappy: isHappy
                    });
                });
            }
        });

        // Build Mermaid diagram
        let diagram = 'stateDiagram-v2\n';

        // Add initial state transitions
        states.filter(s => s.isInitial).forEach(s => {
            diagram += `    [*] --> ${s.name}\n`;
        });

        // Add transitions
        transitions.forEach(t => {
            const label = t.isHappy ? `${t.name} ⭐` : t.name;
            diagram += `    ${t.from} --> ${t.to}: ${label}\n`;
        });

        // Add final state transitions
        states.filter(s => s.isFinal).forEach(s => {
            diagram += `    ${s.name} --> [*]\n`;
        });

        // If no content, show placeholder
        if (states.length === 0) {
            diagram = 'stateDiagram-v2\n    [*] --> new_state\n    new_state --> [*]';
        }

        // Re-render Mermaid
        const previewEl = document.getElementById('mermaidPreview');
        previewEl.removeAttribute('data-processed');
        previewEl.innerHTML = diagram;

        if (typeof mermaid !== 'undefined') {
            mermaid.init(undefined, previewEl);
        }
    }
});
</script>
<?php $this->end(); ?>
