<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use Cake\Http\Response;
use Cake\I18n\DateTime;
use Nette\Neon\Neon;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\TransitionResult;
use Workflow\Engine\WorkflowAnalyzer;
use Workflow\Service\TransitionLogger;

class WorkflowsController extends WorkflowAppController
{
    /**
     * List all workflows.
     *
     * @throws \RuntimeException
     */
    public function index(): void
    {
        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $workflowNames = $this->workflowRegistry->getWorkflowNames();

        $workflows = [];
        foreach ($workflowNames as $name) {
            $definition = $this->workflowRegistry->getWorkflow($name);
            $workflows[] = [
                'name' => $name,
                'definition' => $definition,
                'stateCount' => count($definition->getStates()),
                'transitionCount' => count($definition->getTransitions()),
            ];
        }

        $this->set(compact('workflows'));
    }

    /**
     * View a specific workflow.
     */
    public function view(string $name): void
    {
        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $definition = $this->workflowRegistry->getWorkflow($name);
        $tableName = $definition->getTable();
        $field = $definition->getField();

        // Count items by state
        $stateCounts = [];
        $totalActive = 0;
        try {
            $table = $this->fetchTable($tableName);
            foreach ($definition->getStates() as $state) {
                $stateName = $state->getName();
                $count = $table->find()
                    ->where([$field => $stateName])
                    ->count();
                $stateCounts[$stateName] = $count;
                if (!$state->isFinal()) {
                    $totalActive += $count;
                }
            }
        } catch (Throwable) {
            // Table might not exist
        }

        // Get recent transitions for this workflow
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
        $recentTransitions = $transitionsTable->find()
            ->where(['workflow_name' => $name])
            ->orderBy(['id' => 'DESC'])
            ->limit(20)
            ->toArray();

        // Transitions today
        $transitionsToday = $transitionsTable->find()
            ->where([
                'workflow_name' => $name,
                'created >=' => DateTime::now()->startOfDay(),
            ])
            ->count();

        // Get pending timeouts
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $pendingTimeouts = $timeoutsTable->find()
            ->where([
                'workflow_name' => $name,
                'processed' => false,
            ])
            ->orderBy(['due_at' => 'ASC'])
            ->limit(10)
            ->toArray();

        // Check available export formats
        $exportFormats = static::getAvailableExportFormats();

        $analyzer = new WorkflowAnalyzer();
        $issues = $analyzer->analyze($definition);
        $issuesBySeverity = $analyzer->getIssuesBySeverity();

        $this->set(compact(
            'definition',
            'stateCounts',
            'totalActive',
            'recentTransitions',
            'transitionsToday',
            'pendingTimeouts',
            'exportFormats',
            'issues',
            'issuesBySeverity',
        ));
    }

    /**
     * Matrix view showing items by state and time in state.
     */
    public function matrix(string $name): void
    {
        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $definition = $this->workflowRegistry->getWorkflow($name);
        $tableName = $definition->getTable();
        $field = $definition->getField();

        // Time bucket boundaries (in hours)
        $timeBuckets = [
            '< 1 hour' => 1,
            '1-4 hours' => 4,
            '4-24 hours' => 24,
            '1-7 days' => 168, // 7 * 24
            '> 7 days' => PHP_INT_MAX,
        ];

        $now = DateTime::now();
        $matrix = [];
        $totals = array_fill_keys(array_keys($timeBuckets), 0);
        $stateTotals = [];

        // Initialize matrix structure
        foreach ($definition->getStates() as $state) {
            $stateName = $state->getName();
            $matrix[$stateName] = array_fill_keys(array_keys($timeBuckets), 0);
            $stateTotals[$stateName] = 0;
        }

        try {
            $table = $this->fetchTable($tableName);
            $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');

            // Get all entities with their current state
            /** @var array<\Cake\Datasource\EntityInterface> $entities */
            $entities = $table->find()
                ->select(['id', $field])
                ->toArray();

            if ($entities) {
                // Get the most recent transition for each entity
                $entityIds = array_map(fn ($e) => (string)$e->get('id'), $entities);

                // Build a map of entity_id => last transition time
                /** @var array<string, \Cake\I18n\DateTime> $lastTransitions */
                $lastTransitions = [];
                /** @var array<\Workflow\Model\Entity\WorkflowTransition> $transitions */
                $transitions = $transitionsTable->find()
                    ->where([
                        'workflow_name' => $name,
                        'entity_id IN' => $entityIds,
                    ])
                    ->orderBy(['entity_id' => 'ASC', 'id' => 'DESC'])
                    ->toArray();

                foreach ($transitions as $t) {
                    $entityId = $t->entity_id;
                    // Only keep the first (most recent) transition per entity
                    if (!isset($lastTransitions[$entityId])) {
                        $lastTransitions[$entityId] = $t->created;
                    }
                }

                // Now categorize each entity
                foreach ($entities as $entity) {
                    $state = $entity->get($field);
                    $entityId = (string)$entity->get('id');

                    // Skip if state not in workflow definition
                    if (!isset($matrix[$state])) {
                        continue;
                    }

                    // Determine time in state
                    $enteredAt = $lastTransitions[$entityId] ?? $entity->get('created') ?? $now;
                    $hoursInState = $now->diffInHours($enteredAt, false);

                    // Find appropriate bucket
                    foreach ($timeBuckets as $bucketName => $maxHours) {
                        if ($hoursInState < $maxHours) {
                            $matrix[$state][$bucketName]++;
                            $totals[$bucketName]++;
                            $stateTotals[$state]++;

                            break;
                        }
                    }
                }
            }
        } catch (Throwable) {
            // Table might not exist
        }

        $this->set(compact(
            'definition',
            'matrix',
            'timeBuckets',
            'totals',
            'stateTotals',
        ));
    }

    /**
     * Validate a workflow definition.
     *
     * @throws \RuntimeException
     */
    public function validate(string $name): void
    {
        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $definition = $this->workflowRegistry->getWorkflow($name);

        $analyzer = new WorkflowAnalyzer();
        $issues = $analyzer->analyze($definition);
        $issuesBySeverity = $analyzer->getIssuesBySeverity();

        $errorCount = count($issuesBySeverity['errors']);
        $warningCount = count($issuesBySeverity['warnings']);
        $infoCount = count($issuesBySeverity['info']);

        $this->set(compact(
            'definition',
            'issues',
            'issuesBySeverity',
            'errorCount',
            'warningCount',
            'infoCount',
        ));
    }

    /**
     * Designer view for a workflow.
     *
     * @throws \RuntimeException
     */
    public function designer(string $name): void
    {
        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $definition = $this->workflowRegistry->getWorkflow($name);

        // Build structured data for the designer
        $states = [];
        foreach ($definition->getStates() as $state) {
            $states[] = [
                'name' => $state->getName(),
                'label' => $state->getLabel(),
                'color' => $state->getColor(),
                'isInitial' => $state->isInitial(),
                'isFinal' => $state->isFinal(),
                'isFailed' => $state->isFailed(),
                'flags' => $state->getFlags(),
            ];
        }

        $transitions = [];
        foreach ($definition->getTransitions() as $transition) {
            $transitions[] = [
                'name' => $transition->getName(),
                'from' => $transition->getFrom(),
                'to' => $transition->getTo(),
                'isHappy' => $transition->isHappy(),
                'isAutomatic' => $transition->isAutomatic(),
                'guards' => $transition->getGuards(),
                'commands' => $transition->getCommands(),
            ];
        }

        $this->set(compact('definition', 'states', 'transitions'));
    }

    /**
     * Export workflow definition as NEON or YAML.
     *
     * @param string $name Workflow name
     * @param string|null $format Export format (neon or yaml), auto-detects if not specified
     *
     * @throws \RuntimeException
     */
    public function export(string $name, ?string $format = null): Response
    {
        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        // Determine format based on availability
        $hasNeon = class_exists(Neon::class);
        $hasYaml = class_exists(Yaml::class);

        if ($format === null) {
            $format = $hasNeon ? 'neon' : ($hasYaml ? 'yaml' : null);
        }

        if ($format === 'neon' && !$hasNeon) {
            throw new RuntimeException('nette/neon is not installed');
        }
        if ($format === 'yaml' && !$hasYaml) {
            throw new RuntimeException('symfony/yaml is not installed');
        }
        if ($format === null) {
            throw new RuntimeException('No export format available. Install nette/neon or symfony/yaml.');
        }

        $definition = $this->workflowRegistry->getWorkflow($name);
        $data = $this->buildExportData($definition);

        if ($format === 'neon') {
            $content = Neon::encode($data, true);
            $contentType = 'text/plain';
            $extension = 'neon';
        } else {
            $content = Yaml::dump($data, 6, 2);
            $contentType = 'text/yaml';
            $extension = 'yaml';
        }

        return $this->response
            ->withType($contentType)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $name . '.' . $extension . '"')
            ->withStringBody($content);
    }

    /**
     * Build exportable data structure from definition.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     *
     * @return array<string, mixed>
     */
    private function buildExportData(Definition $definition): array
    {
        $data = [
            $definition->getName() => [
                'table' => $definition->getTable(),
                'field' => $definition->getField(),
                'version' => $definition->getVersion(),
            ],
        ];

        $workflowData = &$data[$definition->getName()];

        if ($definition->getLabel() || $definition->getDescription()) {
            $workflowData['metadata'] = [];
            if ($definition->getLabel()) {
                $workflowData['metadata']['label'] = $definition->getLabel();
            }
            if ($definition->getDescription()) {
                $workflowData['metadata']['description'] = $definition->getDescription();
            }
        }

        // States
        $workflowData['states'] = [];
        foreach ($definition->getStates() as $state) {
            $stateData = [];
            if ($state->getLabel()) {
                $stateData['label'] = $state->getLabel();
            }
            if ($state->getColor()) {
                $stateData['color'] = $state->getColor();
            }
            if ($state->isInitial()) {
                $stateData['initial'] = true;
            }
            if ($state->isFinal()) {
                $stateData['final'] = true;
            }
            if ($state->isFailed()) {
                $stateData['failed'] = true;
            }
            if ($state->getFlags()) {
                $stateData['flags'] = $state->getFlags();
            }

            $workflowData['states'][$state->getName()] = $stateData ?: null;
        }

        // Transitions
        $workflowData['transitions'] = [];
        foreach ($definition->getTransitions() as $transition) {
            $transitionData = [
                'from' => $transition->getFrom(),
                'to' => $transition->getTo(),
            ];
            if ($transition->isHappy()) {
                $transitionData['happy'] = true;
            }
            if ($transition->isAutomatic()) {
                $transitionData['automatic'] = true;
            }
            if ($transition->getGuards()) {
                $transitionData['guard'] = $transition->getGuards()[0] ?? null;
            }
            if ($transition->getCommands()) {
                $transitionData['command'] = $transition->getCommands()[0] ?? null;
            }

            $workflowData['transitions'][$transition->getName()] = $transitionData;
        }

        return $data;
    }

    /**
     * Check which export formats are available.
     *
     * @return array{neon: bool, yaml: bool}
     */
    public static function getAvailableExportFormats(): array
    {
        return [
            'neon' => class_exists(Neon::class),
            'yaml' => class_exists(Yaml::class),
        ];
    }

    /**
     * Create a new workflow using the visual designer.
     *
     * @throws \RuntimeException
     *
     * @return \Cake\Http\Response|null
     */
    public function create(): ?Response
    {
        $exportFormats = static::getAvailableExportFormats();

        if ($this->request->is('post')) {
            $data = $this->request->getData();

            // Build definition structure from form data
            $workflowData = $this->buildWorkflowDataFromForm($data);

            $format = $data['export_format'] ?? 'neon';
            if ($format === 'neon' && !$exportFormats['neon']) {
                $format = 'yaml';
            }
            if ($format === 'yaml' && !$exportFormats['yaml']) {
                throw new RuntimeException('No export format available. Install nette/neon or symfony/yaml.');
            }

            if ($format === 'neon') {
                $content = Neon::encode($workflowData, true);
                $contentType = 'text/plain';
                $extension = 'neon';
            } else {
                $content = Yaml::dump($workflowData, 6, 2);
                $contentType = 'text/yaml';
                $extension = 'yaml';
            }

            $workflowName = $data['name'] ?? 'workflow';

            return $this->response
                ->withType($contentType)
                ->withHeader('Content-Disposition', 'attachment; filename="' . $workflowName . '.' . $extension . '"')
                ->withStringBody($content);
        }

        $this->set(compact('exportFormats'));

        return null;
    }

    /**
     * Build workflow data structure from form submission.
     *
     * @param array<string, mixed> $data Form data
     *
     * @return array<string, mixed>
     */
    private function buildWorkflowDataFromForm(array $data): array
    {
        $workflowName = $data['name'] ?? 'new_workflow';

        $workflowData = [
            $workflowName => [
                'table' => $data['table'] ?? 'Items',
                'field' => $data['field'] ?? 'state',
            ],
        ];

        $workflow = &$workflowData[$workflowName];

        // Add metadata if provided
        if (!empty($data['label']) || !empty($data['description'])) {
            $workflow['metadata'] = [];
            if (!empty($data['label'])) {
                $workflow['metadata']['label'] = $data['label'];
            }
            if (!empty($data['description'])) {
                $workflow['metadata']['description'] = $data['description'];
            }
        }

        // Add version if provided
        if (!empty($data['version'])) {
            $workflow['version'] = $data['version'];
        }

        // Process states
        $workflow['states'] = [];
        if (!empty($data['states']) && is_array($data['states'])) {
            foreach ($data['states'] as $state) {
                if (empty($state['name'])) {
                    continue;
                }
                $stateData = [];
                if (!empty($state['label'])) {
                    $stateData['label'] = $state['label'];
                }
                if (!empty($state['color'])) {
                    $stateData['color'] = $state['color'];
                }
                if (!empty($state['initial'])) {
                    $stateData['initial'] = true;
                }
                if (!empty($state['final'])) {
                    $stateData['final'] = true;
                }
                if (!empty($state['failed'])) {
                    $stateData['failed'] = true;
                }
                if (!empty($state['flags'])) {
                    $flags = array_filter(array_map('trim', explode(',', $state['flags'])));
                    if ($flags) {
                        $stateData['flags'] = $flags;
                    }
                }
                $workflow['states'][$state['name']] = $stateData ?: null;
            }
        }

        // Process transitions
        $workflow['transitions'] = [];
        if (!empty($data['transitions']) && is_array($data['transitions'])) {
            foreach ($data['transitions'] as $transition) {
                if (empty($transition['name'])) {
                    continue;
                }
                $transitionData = [];
                if (!empty($transition['from'])) {
                    $from = array_filter(array_map('trim', explode(',', $transition['from'])));
                    $transitionData['from'] = $from;
                }
                if (!empty($transition['to'])) {
                    $transitionData['to'] = $transition['to'];
                }
                if (!empty($transition['happy'])) {
                    $transitionData['happy'] = true;
                }
                if (!empty($transition['automatic'])) {
                    $transitionData['automatic'] = true;
                }
                if (!empty($transition['guard'])) {
                    $transitionData['guard'] = $transition['guard'];
                }
                if (!empty($transition['command'])) {
                    $transitionData['command'] = $transition['command'];
                }
                $workflow['transitions'][$transition['name']] = $transitionData;
            }
        }

        return $workflowData;
    }

    /**
     * Simulate a transition to see what would happen (dry-run).
     *
     * Shows which guards would block, what the target state would be, etc.
     *
     * @param string $name Workflow name
     * @param string $entityId Entity ID
     * @param string|null $transition Optional specific transition to simulate
     */
    public function simulate(string $name, string $entityId, ?string $transition = null): void
    {
        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $definition = $this->workflowRegistry->getWorkflow($name);
        $tableName = $definition->getTable();
        $field = $definition->getField();

        $table = $this->fetchTable($tableName);
        $entity = $table->get($entityId);

        $currentState = $entity->get($field);
        $engine = $this->workflowRegistry->getEngine($name);

        // Get all transitions and simulate each one
        $simulationResults = [];

        $transitionsToSimulate = $transition
            ? [$definition->getTransition($transition)]
            : $definition->getTransitions();

        foreach ($transitionsToSimulate as $t) {
            $transitionName = $t->getName();
            $fromStates = $t->getFrom();

            // Check if transition is applicable from current state
            $isFromStateValid = in_array($currentState, $fromStates, true);

            // Simulate by calling can() with detailed context
            $canApply = $engine->can($definition, $entity, $transitionName);

            // Try to get detailed guard results
            $guardResults = [];
            foreach ($t->getGuards() as $guardName) {
                // We can't easily get individual guard results without modifying the engine,
                // so we'll just indicate whether the overall transition can be applied
                $guardResults[$guardName] = $canApply ? 'passed' : 'unknown';
            }

            $simulationResults[] = [
                'name' => $transitionName,
                'from' => $fromStates,
                'to' => $t->getTo(),
                'is_from_state_valid' => $isFromStateValid,
                'can_apply' => $canApply,
                'guards' => $t->getGuards(),
                'guard_results' => $guardResults,
                'commands' => $t->getCommands(),
                'is_automatic' => $t->isAutomatic(),
                'is_happy' => $t->isHappy(),
            ];
        }

        // Get available transitions (those that can actually be applied)
        $availableTransitions = $engine->getAvailableTransitions($definition, $entity);

        $this->set(compact(
            'definition',
            'entity',
            'entityId',
            'currentState',
            'simulationResults',
            'availableTransitions',
            'transition',
        ));
    }

    /**
     * Force a transition, bypassing guards.
     *
     * Use with caution - this skips all guard checks.
     *
     * @param string $name Workflow name
     * @param string $entityId Entity ID
     */
    public function forceTransition(string $name, string $entityId): ?Response
    {
        $this->request->allowMethod(['get', 'post']);

        if ($this->workflowRegistry === null) {
            throw new RuntimeException('Workflow registry not configured');
        }

        $definition = $this->workflowRegistry->getWorkflow($name);
        $tableName = $definition->getTable();
        $field = $definition->getField();

        $table = $this->fetchTable($tableName);
        $entity = $table->get($entityId);

        $currentState = $entity->get($field);

        // Get transitions that could apply from current state (regardless of guards)
        $applicableTransitions = [];
        foreach ($definition->getTransitions() as $t) {
            if (in_array($currentState, $t->getFrom(), true)) {
                $applicableTransitions[$t->getName()] = sprintf(
                    '%s → %s',
                    $t->getName(),
                    $t->getTo(),
                );
            }
        }

        if ($this->request->is('post')) {
            $transitionName = $this->request->getData('transition');
            $reason = $this->request->getData('reason');

            if (!$transitionName || !isset($applicableTransitions[$transitionName])) {
                $this->Flash->error('Please select a valid transition.');

                return null;
            }

            $transition = $definition->getTransition($transitionName);
            $toState = $transition->getTo();

            // Directly set the new state (bypassing workflow engine)
            $entity->set($field, $toState);

            // Disable workflow validation if attached
            if ($table->hasBehavior('Workflow')) {
                $table->behaviors()->get('Workflow')->setConfig('validateOnSave', false);
            }

            try {
                if ($table->save($entity)) {
                    // Log the forced transition
                    $this->logForcedTransition(
                        $name,
                        $tableName,
                        $entityId,
                        $transitionName,
                        $currentState,
                        $toState,
                        $reason,
                        (string)$definition->getVersion(),
                    );

                    $this->Flash->success(sprintf(
                        'Transition "%s" forced: %s → %s',
                        $transitionName,
                        $currentState,
                        $toState,
                    ));

                    return $this->redirect(['action' => 'view', $name]);
                }

                $this->Flash->error('Could not save entity.');
            } finally {
                if ($table->hasBehavior('Workflow')) {
                    $table->behaviors()->get('Workflow')->setConfig('validateOnSave', true);
                }
            }
        }

        $this->set(compact(
            'definition',
            'entity',
            'entityId',
            'currentState',
            'applicableTransitions',
        ));

        return null;
    }

    /**
     * Log a forced transition.
     */
    private function logForcedTransition(
        string $workflow,
        string $tableName,
        string $entityId,
        string $transitionName,
        string $fromState,
        string $toState,
        ?string $reason,
        string $version,
    ): void {
        try {
            $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
            $transition = $transitionsTable->newEntity([
                'workflow_name' => $workflow,
                'entity_table' => $tableName,
                'entity_id' => $entityId,
                'transition_name' => $transitionName,
                'from_state' => $fromState,
                'to_state' => $toState,
                'workflow_version' => $version,
                'context' => json_encode([
                    'type' => 'forced_transition',
                    'reason' => $reason,
                    'admin_action' => true,
                    'guards_bypassed' => true,
                ]),
            ]);
            $transitionsTable->save($transition);
        } catch (Throwable) {
            // Logging failure should not break the operation
        }
    }
}
