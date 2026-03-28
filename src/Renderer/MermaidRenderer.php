<?php

declare(strict_types=1);

namespace Workflow\Renderer;

use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;

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

    public function render(Definition $definition): string
    {
        $lines = ['stateDiagram-v2'];

        // Add state definitions
        foreach ($definition->getStates() as $state) {
            $lines[] = $this->renderState($state);
        }

        // Add initial state marker
        $initial = $definition->getInitialState();
        $lines[] = "    [*] --> {$initial->getName()}";

        // Add transitions (happy path first for visual emphasis)
        $happyTransitions = [];
        $normalTransitions = [];

        foreach ($definition->getTransitions() as $transition) {
            if ($transition->isHappy()) {
                $happyTransitions[] = $transition;
            } else {
                $normalTransitions[] = $transition;
            }
        }

        foreach ($happyTransitions as $transition) {
            $lines = array_merge($lines, $this->renderTransition($transition, true));
        }
        foreach ($normalTransitions as $transition) {
            $lines = array_merge($lines, $this->renderTransition($transition, false));
        }

        // Add final state markers
        foreach ($definition->getFinalStates() as $state) {
            $lines[] = "    {$state->getName()} --> [*]";
        }

        // Add styling for state types
        $lines = array_merge($lines, $this->renderStyles($definition));

        return implode("\n", $lines);
    }

    private function renderState(State $state): string
    {
        $name = $state->getName();
        $label = $state->getDisplayName();

        if ($label !== $name) {
            return "    {$name}: {$label}";
        }

        return "    state {$name}";
    }

    /**
     * @return array<string>
     */
    private function renderTransition(Transition $transition, bool $isHappy): array
    {
        $lines = [];
        $name = $transition->getName();
        $to = $transition->getTo();

        foreach ($transition->getFrom() as $from) {
            if ($isHappy) {
                // Use thick arrow for happy path
                $lines[] = "    {$from} ==> {$to}: {$name}";
            } else {
                $lines[] = "    {$from} --> {$to}: {$name}";
            }
        }

        return $lines;
    }

    /**
     * @return array<string>
     */
    private function renderStyles(Definition $definition): array
    {
        $lines = [];

        foreach ($definition->getStates() as $state) {
            $name = $state->getName();

            // Use explicit color if set
            $color = $state->getColor();
            if ($color !== null) {
                $lines[] = "    style {$name} fill:{$color}";

                continue;
            }

            // Apply type-based default colors
            if ($state->isFailed()) {
                $lines[] = "    style {$name} fill:{$this->colors['failed']},stroke:#CC0000,stroke-width:2px";
            } elseif ($state->isFinal()) {
                $lines[] = "    style {$name} fill:{$this->colors['final']}";
            } elseif ($state->isInitial()) {
                $lines[] = "    style {$name} fill:{$this->colors['initial']},stroke:#228B22,stroke-width:2px";
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
        $lines = ['stateDiagram-v2'];

        // Add state definitions
        foreach ($definition->getStates() as $state) {
            $lines[] = $this->renderState($state);
        }

        // Add initial state marker
        $initial = $definition->getInitialState();
        $lines[] = "    [*] --> {$initial->getName()}";

        // Add transitions
        foreach ($definition->getTransitions() as $transition) {
            $lines = array_merge($lines, $this->renderTransition($transition, $transition->isHappy()));
        }

        // Add final state markers
        foreach ($definition->getFinalStates() as $state) {
            $lines[] = "    {$state->getName()} --> [*]";
        }

        // Add styling
        $lines = array_merge($lines, $this->renderStyles($definition));

        // Mark unreachable states
        foreach ($unreachableStates as $stateName) {
            $lines[] = "    style {$stateName} fill:{$this->colors['unreachable']},stroke:#999,stroke-dasharray:5";
        }

        return implode("\n", $lines);
    }
}
