<?php

declare(strict_types=1);

namespace Workflow\Renderer;

use Cake\Core\Configure;
use RuntimeException;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;

class GraphvizRenderer
{
    public function render(
        Definition $definition,
        ?string $currentState = null,
        bool $showDetails = false,
        string $detailMarkers = 'ascii',
        string $format = 'svg',
    ): string {
        if (!in_array($format, ['svg', 'png'], true)) {
            throw new RuntimeException('Unsupported GraphViz export format `' . $format . '`.');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'workflow-graphviz-');
        if ($tmpFile === false) {
            throw new RuntimeException('Unable to allocate temporary file for workflow export.');
        }
        $outputFile = $tmpFile . '.' . $format;

        try {
            $dot = $this->buildDot($definition, $currentState, $showDetails, $detailMarkers);
            $process = proc_open(
                [
                    $this->resolveDotBinary(),
                    '-T' . $format,
                    '-o',
                    $outputFile,
                ],
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Unable to start GraphViz process.');
            }

            fwrite($pipes[0], $dot);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]) ?: '';
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0 || !is_file($outputFile)) {
                $message = trim($stderr) !== '' ? trim($stderr) : trim($stdout);

                throw new RuntimeException('GraphViz workflow export failed' . ($message !== '' ? ': ' . $message : '.'));
            }

            $result = file_get_contents($outputFile);
            if ($result === false) {
                throw new RuntimeException('Unable to read GraphViz export output.');
            }

            return $result;
        } finally {
            @unlink($tmpFile);
            @unlink($outputFile);
        }
    }

    protected function resolveDotBinary(): string
    {
        $configured = trim((string)Configure::read('Workflow.graphvizBinary', ''));
        if ($configured !== '') {
            return $configured;
        }

        $path = trim((string)shell_exec('command -v dot 2>/dev/null'));
        if ($path === '') {
            throw new RuntimeException('GraphViz `dot` binary is not available for workflow export.');
        }

        return $path;
    }

    protected function buildDot(
        Definition $definition,
        ?string $currentState,
        bool $showDetails,
        string $detailMarkers,
    ): string {
        $lines = [];
        $lines[] = 'digraph workflow {';
        $lines[] = '  rankdir=TB;';
        $lines[] = '  graph [bgcolor="white", pad="0.3", ranksep="0.65", nodesep="0.45"];';
        $lines[] = '  node [shape=box, style="rounded,filled", fontname="Verdana", fontsize=14, color="#9370DB", fillcolor="#ECECFF", fontcolor="#333333", penwidth=1.5];';
        $lines[] = '  edge [fontname="Verdana", fontsize=12, color="#333333", fontcolor="#333333", arrowsize=0.8];';

        foreach ($definition->getStates() as $state) {
            $attributes = $this->buildStateAttributes($state, $currentState);
            $lines[] = sprintf(
                '  %s [%s];',
                $this->stateId($state->getName()),
                $this->formatAttributes($attributes),
            );
        }

        foreach ($definition->getTransitions() as $transition) {
            $attributes = $this->buildTransitionAttributes($transition, $showDetails, $detailMarkers);
            foreach ($transition->getFrom() as $fromState) {
                $lines[] = sprintf(
                    '  %s -> %s [%s];',
                    $this->stateId($fromState),
                    $this->stateId($transition->getTo()),
                    $this->formatAttributes($attributes),
                );
            }
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string>
     */
    protected function buildStateAttributes(State $state, ?string $currentState): array
    {
        $attributes = [
            'label' => $state->getDisplayName(),
        ];

        if ($state->isFailed()) {
            $attributes['fillcolor'] = '#ffebee';
            $attributes['color'] = '#f44336';
            $attributes['penwidth'] = '2';
        } elseif ($state->isFinal()) {
            $attributes['fillcolor'] = '#e8f5e9';
            $attributes['color'] = '#4caf50';
            $attributes['penwidth'] = '2';
        } elseif ($state->isInitial()) {
            $attributes['fillcolor'] = '#f5f5f5';
            $attributes['color'] = '#9e9e9e';
            $attributes['penwidth'] = '2';
        }

        if ($currentState !== null && $state->getName() === $currentState) {
            $attributes['fillcolor'] = '#ffc107';
            $attributes['color'] = '#ff9800';
            $attributes['penwidth'] = '3';
        }

        if ($state->isUnknown()) {
            $attributes['fillcolor'] = '#f1f3f5';
            $attributes['color'] = '#6c757d';
            $attributes['style'] = '"rounded,dashed,filled"';
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    protected function buildTransitionAttributes(Transition $transition, bool $showDetails, string $detailMarkers): array
    {
        $attributes = [
            'label' => $this->buildTransitionLabel($transition, $showDetails, $detailMarkers),
        ];

        if ($transition->isHappy()) {
            $attributes['color'] = '#2e7d32';
            $attributes['fontcolor'] = '#2e7d32';
            $attributes['penwidth'] = '2';
        }

        if ($transition->isAutomatic()) {
            $attributes['style'] = 'dashed';
        }

        return $attributes;
    }

    protected function buildTransitionLabel(Transition $transition, bool $showDetails, string $detailMarkers): string
    {
        $lines = [$transition->getName()];
        if (!$showDetails) {
            return implode('\n', $lines);
        }

        $markerMap = match ($detailMarkers) {
            'emoji' => ['guard' => '🛡 ', 'command' => '⚙ ', 'condition' => '❓ '],
            'none' => ['guard' => '', 'command' => '', 'condition' => ''],
            default => ['guard' => '[G] ', 'command' => '[C] ', 'condition' => '[?] '],
        };

        foreach ($transition->getGuards() as $guard) {
            $lines[] = $markerMap['guard'] . $guard;
        }
        foreach ($transition->getCommands() as $command) {
            $lines[] = $markerMap['command'] . $command;
        }
        if ($transition->getCondition() !== null) {
            $lines[] = $markerMap['condition'] . $transition->getCondition();
        }

        return implode('\n', $lines);
    }

    protected function stateId(string $state): string
    {
        return 'state_' . preg_replace('/[^A-Za-z0-9_]/', '_', $state);
    }

    /**
     * @param array<string, string> $attributes
     */
    protected function formatAttributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            if ($value !== '' && $value[0] === '"' && str_ends_with($value, '"')) {
                $parts[] = $key . '=' . $value;

                continue;
            }

            $escaped = addcslashes($value, "\\\"\n\r");
            $escaped = str_replace(["\n", "\r"], ['\n', ''], $escaped);
            $parts[] = $key . '="' . $escaped . '"';
        }

        return implode(', ', $parts);
    }
}
