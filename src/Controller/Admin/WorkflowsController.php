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
use Workflow\Engine\WorkflowAnalyzer;

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

        $this->set(compact(
            'definition',
            'stateCounts',
            'totalActive',
            'recentTransitions',
            'transitionsToday',
            'pendingTimeouts',
            'exportFormats',
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
}
