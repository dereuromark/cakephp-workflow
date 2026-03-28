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
            $states[] = $this->buildState($stateName, $stateData ?? []);
        }

        $transitions = [];
        foreach ($data['transitions'] ?? [] as $transitionName => $transitionData) {
            $transitions[] = $this->buildTransition($transitionName, $transitionData);
        }

        return new Definition(
            name: $name,
            table: $data['table'] ?? throw new WorkflowException("Missing 'table' in workflow '{$name}'"),
            field: $data['field'] ?? 'state',
            states: $states,
            transitions: $transitions,
            label: $data['metadata']['label'] ?? null,
            description: $data['metadata']['description'] ?? null,
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
            label: $data['label'] ?? null,
            color: $data['color'] ?? null,
            initial: $data['initial'] ?? false,
            final: $data['final'] ?? false,
            failed: $data['failed'] ?? false,
            flags: $data['flags'] ?? [],
        );
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
        }

        return new Transition(
            name: $name,
            from: $from,
            to: $data['to'] ?? throw new WorkflowException("Missing 'to' in transition '{$name}'"),
            happy: $data['happy'] ?? false,
            guards: isset($data['guard']) ? [$data['guard']] : [],
            commands: isset($data['command']) ? [$data['command']] : [],
        );
    }
}
