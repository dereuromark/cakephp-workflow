<?php

declare(strict_types=1);

namespace Workflow\Loader;

use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\StateTimeout;
use Workflow\Engine\Definition\Transition;
use Workflow\Exception\WorkflowException;

/**
 * Loads a whole workflow from a single native-PHP definition file — no extra
 * dependency required (unlike NEON/YAML). Each file in the config path returns
 * `[$workflowName => [...]]`:
 *
 * ```php
 * // config/workflows/order.php
 * return [
 *     'order' => [
 *         'table' => 'Orders',
 *         'field' => 'state',
 *         'states' => [
 *             'pending' => ['initial' => true],
 *             'paid' => ['final' => true],
 *         ],
 *         'transitions' => [
 *             'pay' => ['from' => 'pending', 'to' => 'paid', 'happy' => true],
 *         ],
 *     ],
 * ];
 * ```
 */
class PhpLoader implements LoaderInterface
{
    /**
     * @var array<string, \Workflow\Engine\Definition\Definition>
     */
    private array $definitions = [];

    /**
     * @var array<string, string>
     */
    private array $files = [];

    private bool $scanned = false;

    public function __construct(private readonly string $path)
    {
    }

    public function supports(string $workflowName): bool
    {
        $this->ensureScanned();

        return isset($this->files[$workflowName]);
    }

    public function load(string $workflowName): Definition
    {
        if (isset($this->definitions[$workflowName])) {
            return $this->definitions[$workflowName];
        }

        $this->ensureScanned();

        if (!isset($this->files[$workflowName])) {
            throw new WorkflowException("Workflow '{$workflowName}' not found in PHP files");
        }

        $data = $this->readFile($this->files[$workflowName]);
        if (!isset($data[$workflowName]) || !is_array($data[$workflowName])) {
            throw new WorkflowException("PHP file does not contain workflow '{$workflowName}'");
        }

        return $this->definitions[$workflowName] = $this->buildDefinition($workflowName, $data[$workflowName]);
    }

    /**
     * @return array<string>
     */
    public function getWorkflowNames(): array
    {
        $this->ensureScanned();

        return array_keys($this->files);
    }

    private function ensureScanned(): void
    {
        if ($this->scanned) {
            return;
        }

        $this->scanned = true;
        if (!is_dir($this->path)) {
            return;
        }

        foreach (glob($this->path . DS . '*.php') ?: [] as $file) {
            $this->files[pathinfo($file, PATHINFO_FILENAME)] = $file;
        }
    }

    /**
     * Execute the definition file in an isolated scope and return its array.
     *
     * @return array<string, mixed>
     */
    private function readFile(string $file): array
    {
        $loader = static fn (string $path): mixed => require $path;
        $data = $loader($file);

        return is_array($data) ? $data : [];
    }

    /**
     * @param string $name
     * @param array<string, mixed> $data
     *
     * @throws \Workflow\Exception\WorkflowException
     */
    private function buildDefinition(string $name, array $data): Definition
    {
        $states = [];
        foreach ($data['states'] ?? [] as $stateName => $stateData) {
            $states[] = $this->buildState((string)$stateName, is_array($stateData) ? $stateData : []);
        }

        $transitions = [];
        foreach ($data['transitions'] ?? [] as $transitionName => $transitionData) {
            $transitions[] = $this->buildTransition((string)$transitionName, is_array($transitionData) ? $transitionData : []);
        }

        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];

        return new Definition(
            name: $name,
            table: $data['table'] ?? throw new WorkflowException("Missing 'table' in workflow '{$name}'"),
            field: $data['field'] ?? 'state',
            states: $states,
            transitions: $transitions,
            label: isset($metadata['label']) && is_string($metadata['label']) ? $metadata['label'] : null,
            description: isset($metadata['description']) && is_string($metadata['description']) ? $metadata['description'] : null,
            version: (int)($data['version'] ?? 1),
        );
    }

    /**
     * @param string $name
     * @param array<string, mixed> $data
     */
    private function buildState(string $name, array $data): State
    {
        return new State(
            name: $name,
            label: isset($data['label']) && is_string($data['label']) ? $data['label'] : null,
            color: isset($data['color']) && is_string($data['color']) ? $data['color'] : null,
            initial: (bool)($data['initial'] ?? false),
            final: (bool)($data['final'] ?? false),
            failed: (bool)($data['failed'] ?? false),
            flags: $this->toStringList($data['flags'] ?? []),
            onEnter: $this->toStringList($data['onEnter'] ?? []),
            onExit: $this->toStringList($data['onExit'] ?? []),
            requireReasonFor: $this->toStringList($data['requireReasonFor'] ?? []),
            timeouts: $this->buildTimeouts(isset($data['timeouts']) && is_array($data['timeouts']) ? $data['timeouts'] : []),
        );
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<\Workflow\Engine\Definition\StateTimeout>
     */
    private function buildTimeouts(array $data): array
    {
        $timeouts = [];
        foreach ($data as $key => $timeoutData) {
            if (is_array($timeoutData)) {
                $after = $timeoutData['after'] ?? null;
                $transition = $timeoutData['transition'] ?? null;
            } elseif (is_string($key) && is_string($timeoutData)) {
                $after = $key;
                $transition = $timeoutData;
            } else {
                continue;
            }

            if (!is_string($after) || !is_string($transition) || $after === '' || $transition === '') {
                continue;
            }

            $timeouts[] = new StateTimeout($after, $transition);
        }

        return $timeouts;
    }

    /**
     * @param string $name
     * @param array<string, mixed> $data
     *
     * @throws \Workflow\Exception\WorkflowException
     */
    private function buildTransition(string $name, array $data): Transition
    {
        $from = $data['from'] ?? [];
        $from = is_array($from) ? $this->toStringList($from) : [(string)$from];

        $to = $data['to'] ?? throw new WorkflowException("Missing 'to' in transition '{$name}'");

        return new Transition(
            name: $name,
            from: $from,
            to: (string)$to,
            happy: (bool)($data['happy'] ?? false),
            guards: isset($data['guard']) ? [(string)$data['guard']] : $this->toStringList($data['guards'] ?? []),
            commands: isset($data['command']) ? [(string)$data['command']] : $this->toStringList($data['commands'] ?? []),
            condition: isset($data['condition']) ? (string)$data['condition'] : null,
            automatic: (bool)($data['automatic'] ?? false),
        );
    }

    /**
     * @param mixed $value
     *
     * @return array<string>
     */
    private function toStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map(static fn ($v): string => (string)$v, array_values($value));
    }
}
