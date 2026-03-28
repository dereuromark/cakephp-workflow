<?php

declare(strict_types=1);

namespace Workflow\Loader;

use Nette\Neon\Neon;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Exception\WorkflowException;

class NeonLoader implements LoaderInterface
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

    public function __construct(private string $path)
    {
        if (!class_exists(Neon::class)) {
            throw new WorkflowException(
                'nette/neon is required for NEON workflow definitions. '
                . 'Install it via: composer require nette/neon',
            );
        }
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
            throw new WorkflowException("Workflow '{$workflowName}' not found in NEON files");
        }

        $content = (string)file_get_contents($this->files[$workflowName]);
        $data = Neon::decode($content);

        if (!isset($data[$workflowName])) {
            throw new WorkflowException("NEON file does not contain workflow '{$workflowName}'");
        }

        $this->definitions[$workflowName] = $this->buildDefinition($workflowName, $data[$workflowName]);

        return $this->definitions[$workflowName];
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

        if (!is_dir($this->path)) {
            $this->scanned = true;

            return;
        }

        $files = glob($this->path . DS . '*.neon') ?: [];

        foreach ($files as $file) {
            $basename = pathinfo($file, PATHINFO_FILENAME);
            $this->files[$basename] = $file;
        }

        $this->scanned = true;
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
            $states[] = $this->buildState($stateName, is_array($stateData) ? $stateData : []);
        }

        $transitions = [];
        foreach ($data['transitions'] ?? [] as $transitionName => $transitionData) {
            $transitions[] = $this->buildTransition($transitionName, is_array($transitionData) ? $transitionData : []);
        }

        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];

        return new Definition(
            name: $name,
            table: $data['table'] ?? throw new WorkflowException("Missing 'table' in workflow '{$name}'"),
            field: $data['field'] ?? 'state',
            states: $states,
            transitions: $transitions,
            label: $this->extractString($metadata['label'] ?? null),
            description: $this->extractString($metadata['description'] ?? null),
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
            label: $this->extractString($data['label'] ?? null),
            color: $this->extractString($data['color'] ?? null),
            initial: (bool)($data['initial'] ?? false),
            final: (bool)($data['final'] ?? false),
            failed: (bool)($data['failed'] ?? false),
            flags: $this->extractArray($data['flags'] ?? []),
            onEnter: $this->extractArray($data['onEnter'] ?? []),
            onExit: $this->extractArray($data['onExit'] ?? []),
            requireReasonFor: $this->extractArray($data['requireReasonFor'] ?? []),
        );
    }

    /**
     * Extract a string value, handling Nette\Neon\Entity objects.
     */
    private function extractString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        return null;
    }

    /**
     * Extract an array value, handling Nette\Neon\Entity objects.
     *
     * @return array<string>
     */
    private function extractArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(fn ($v) => is_string($v) ? $v : (string)$v, $value);
        }

        return [];
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
        if (is_string($from)) {
            $from = [$from];
        } elseif (is_array($from)) {
            $from = array_map(fn ($v) => is_string($v) ? $v : (string)$v, $from);
        } else {
            $from = [(string)$from];
        }

        $to = $data['to'] ?? throw new WorkflowException("Missing 'to' in transition '{$name}'");
        if (!is_string($to)) {
            $to = (string)$to;
        }

        $guard = $data['guard'] ?? null;
        $guards = [];
        if ($guard !== null) {
            $guards = [is_string($guard) ? $guard : (string)$guard];
        }

        $command = $data['command'] ?? null;
        $commands = [];
        if ($command !== null) {
            $commands = [is_string($command) ? $command : (string)$command];
        }

        $condition = $data['condition'] ?? null;
        if ($condition !== null && !is_string($condition)) {
            $condition = (string)$condition;
        }

        return new Transition(
            name: $name,
            from: $from,
            to: $to,
            happy: (bool)($data['happy'] ?? false),
            guards: $guards,
            commands: $commands,
            condition: $condition,
            automatic: (bool)($data['automatic'] ?? false),
        );
    }
}
