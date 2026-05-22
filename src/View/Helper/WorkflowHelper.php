<?php

declare(strict_types=1);

namespace Workflow\View\Helper;

use Cake\Datasource\EntityInterface;
use Cake\View\Helper;
use Workflow\Engine\Definition\Definition;
use Workflow\Renderer\MermaidRenderer;

/**
 * @property \Cake\View\Helper\HtmlHelper $Html
 */
class WorkflowHelper extends Helper
{
    protected array $helpers = ['Html'];

    private ?MermaidRenderer $mermaidRenderer = null;

    /**
     * Render a Mermaid diagram for a workflow.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param array<string, mixed> $options
     */
    public function diagram(Definition $definition, array $options = []): string
    {
        $renderer = $this->getMermaidRenderer();
        $mermaid = $renderer->render($definition);

        $divId = $options['id'] ?? 'workflow-diagram-' . $definition->getName();
        $class = $options['class'] ?? 'mermaid';

        return sprintf(
            '<div id="%s" class="%s">%s</div>',
            h($divId),
            h($class),
            $mermaid,
        );
    }

    /**
     * Render available transitions as buttons.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string> $transitions
     * @param array<string, mixed> $options
     */
    public function transitionButtons(
        EntityInterface $entity,
        array $transitions,
        array $options = [],
    ): string {
        if (!$transitions) {
            return '';
        }

        $urlBase = $options['url'] ?? [];
        $buttonClass = $options['buttonClass'] ?? 'btn btn-sm btn-outline-primary';

        $buttons = [];
        foreach ($transitions as $transition) {
            $url = $urlBase + ['action' => 'transition', $entity->get('id'), $transition];
            $buttons[] = $this->Html->link(
                ucfirst($transition),
                $url,
                [
                    'class' => $buttonClass,
                    'data-transition' => $transition,
                ],
            );
        }

        return implode(' ', $buttons);
    }

    /**
     * Render the current state as a badge.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param string $state
     * @param array<string, mixed> $options
     */
    public function stateBadge(Definition $definition, string $state, array $options = []): string
    {
        $stateObj = $definition->resolveState($state);
        $color = $stateObj->getColor() ?? '#6c757d';
        $label = $stateObj->getDisplayName();

        $style = sprintf('background-color: %s; color: %s;', $color, $this->getContrastColor($color));
        $class = $options['class'] ?? 'badge';

        return sprintf(
            '<span class="%s" style="%s">%s</span>',
            h($class),
            h($style),
            h($label),
        );
    }

    /**
     * Get the color for a state.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     * @param string $state
     */
    public function getStateColor(Definition $definition, string $state): string
    {
        $stateObj = $definition->resolveState($state);

        return $stateObj->getColor() ?? '#6c757d';
    }

    /**
     * Get the raw Mermaid code for a workflow.
     *
     * @param \Workflow\Engine\Definition\Definition $definition
     */
    public function getMermaidCode(Definition $definition): string
    {
        $renderer = $this->getMermaidRenderer();

        return $renderer->render($definition);
    }

    /**
     * Include Mermaid.js library.
     */
    public function includeMermaid(): string
    {
        return '<script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>'
            . '<script>mermaid.initialize({startOnLoad:true});</script>';
    }

    private function getMermaidRenderer(): MermaidRenderer
    {
        if ($this->mermaidRenderer === null) {
            $this->mermaidRenderer = new MermaidRenderer();
        }

        return $this->mermaidRenderer;
    }

    /**
     * Get a contrasting text color for a background.
     */
    private function getContrastColor(string $hexColor): string
    {
        $hex = ltrim($hexColor, '#');

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }
}
