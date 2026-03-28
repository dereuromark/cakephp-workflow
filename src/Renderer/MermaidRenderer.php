<?php

declare(strict_types=1);

namespace Workflow\Renderer;

use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;

class MermaidRenderer implements RendererInterface
{
    // Default colors for state types (can be overridden via config)
    /**
     * @var array
     */
    private const DEFAULT_COLORS = [
        'initial' => '#90EE90', // Light green
        'final' => '#87CEEB', // Light blue
        'failed' => '#FF6B6B', // Light red
        'unreachable' => '#D3D3D3', // Light gray
    ];

    /**
     * @var array<string, string>
     */
    private array $colors;

    /**
     * @param array<string, string> $colors Custom colors for state types
     */
    public function __construct(array $colors = [])
    {
        $this->colors = array_merge(self::DEFAULT_COLORS, $colors);
    }

    /**
     * Render the workflow diagram.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param string|null $currentState Current state to highlight
     */
    public function render(Definition $definition, ?string $currentState = null): string
    {
        $lines = ['flowchart TD'];
        $linkIndex = 0;
        $happyLinkIndices = [];

        // Add state node definitions
        foreach ($definition->getStates() as $state) {
            $lines[] = $this->renderState($state);
        }

        // Add transitions and track happy path indices
        foreach ($definition->getTransitions() as $transition) {
            $name = $transition->getName();
            $to = $transition->getTo();
            $isHappy = $transition->isHappy();

            foreach ($transition->getFrom() as $from) {
                $lines[] = "    {$from} -->|{$name}| {$to}";
                if ($isHappy) {
                    $happyLinkIndices[] = $linkIndex;
                }
                $linkIndex++;
            }
        }

        // Add styling for state types
        $lines = array_merge($lines, $this->renderStyles($definition, $currentState));

        // Style happy path links green
        if ($happyLinkIndices) {
            $indices = implode(',', $happyLinkIndices);
            $lines[] = "    linkStyle {$indices} stroke:#2e7d32,stroke-width:2px";
        }

        return implode("\n", $lines);
    }

    private function renderState(State $state): string
    {
        $name = $state->getName();
        $label = $state->getDisplayName();

        // Use stadium shape for states (rounded rectangle)
        return "    {$name}([{$label}])";
    }

    /**
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param string|null $currentState State to highlight as current
     *
     * @return array<string>
     */
    private function renderStyles(Definition $definition, ?string $currentState = null): array
    {
        $lines = [];

        // Define class definitions
        $lines[] = '    classDef current fill:#ffc107,stroke:#ff9800,stroke-width:3px,font-weight:bold';
        $lines[] = '    classDef initial fill:#f5f5f5,stroke:#9e9e9e,stroke-width:2px';
        $lines[] = '    classDef final fill:#e8f5e9,stroke:#4caf50,stroke-width:2px';
        $lines[] = '    classDef failed fill:#ffebee,stroke:#f44336,stroke-width:2px';

        foreach ($definition->getStates() as $state) {
            $name = $state->getName();

            // Current state gets special highlight (overrides all other styling)
            if ($currentState !== null && $name === $currentState) {
                $lines[] = "    class {$name} current";

                continue;
            }

            // Assign class based on state type
            if ($state->isFailed()) {
                $lines[] = "    class {$name} failed";
            } elseif ($state->isFinal()) {
                $lines[] = "    class {$name} final";
            } elseif ($state->isInitial()) {
                $lines[] = "    class {$name} initial";
            }
        }

        return $lines;
    }

    /**
     * Render with unreachable states highlighted.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param array<string> $unreachableStates
     */
    public function renderWithAnalysis(Definition $definition, array $unreachableStates = []): string
    {
        $lines = ['flowchart TD'];
        $linkIndex = 0;
        $happyLinkIndices = [];

        // Add state node definitions
        foreach ($definition->getStates() as $state) {
            $lines[] = $this->renderState($state);
        }

        // Add transitions and track happy path indices
        foreach ($definition->getTransitions() as $transition) {
            $name = $transition->getName();
            $to = $transition->getTo();
            $isHappy = $transition->isHappy();

            foreach ($transition->getFrom() as $from) {
                $lines[] = "    {$from} -->|{$name}| {$to}";
                if ($isHappy) {
                    $happyLinkIndices[] = $linkIndex;
                }
                $linkIndex++;
            }
        }

        // Add styling
        $lines = array_merge($lines, $this->renderStyles($definition));

        // Style happy path links green
        if ($happyLinkIndices) {
            $indices = implode(',', $happyLinkIndices);
            $lines[] = "    linkStyle {$indices} stroke:#2e7d32,stroke-width:2px";
        }

        // Mark unreachable states
        if ($unreachableStates) {
            $lines[] = "    classDef unreachable fill:{$this->colors['unreachable']},stroke:#999,stroke-dasharray:5 5";
            foreach ($unreachableStates as $stateName) {
                $lines[] = "    class {$stateName} unreachable";
            }
        }

        return implode("\n", $lines);
    }
}
