<?php

declare(strict_types=1);

namespace Workflow\Loader;

use Symfony\Component\Yaml\Yaml;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Exception\WorkflowException;

class YamlLoader implements LoaderInterface
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
        if (!class_exists(Yaml::class)) {
            throw new WorkflowException(
                'symfony/yaml is required for YAML workflow definitions. '
                . 'Install it via: composer require symfony/yaml',
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
            throw new WorkflowException("Workflow '{$workflowName}' not found in YAML files");
        }

        $content = (string)file_get_contents($this->files[$workflowName]);
        $data = Yaml::parse($content);

        if (!isset($data[$workflowName])) {
            throw new WorkflowException("YAML file does not contain workflow '{$workflowName}'");
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

        $files = glob($this->path . DS . '*.yaml') ?: [];
        $files = array_merge($files, glob($this->path . DS . '*.yml') ?: []);

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
            label: isset($metadata['label']) && is_string($metadata['label']) ? $metadata['label'] : null,
            description: isset($metadata['description']) && is_string($metadata['description']) ? $metadata['description'] : null,
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
            flags: isset($data['flags']) && is_array($data['flags']) ? $data['flags'] : [],
            onEnter: isset($data['onEnter']) && is_array($data['onEnter']) ? $data['onEnter'] : [],
            onExit: isset($data['onExit']) && is_array($data['onExit']) ? $data['onExit'] : [],
            requireReasonFor: isset($data['requireReasonFor']) && is_array($data['requireReasonFor']) ? $data['requireReasonFor'] : [],
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
