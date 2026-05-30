<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Renderer;

use PHPUnit\Framework\TestCase;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Renderer\MermaidRenderer;

class MermaidRendererTest extends TestCase
{
    private MermaidRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new MermaidRenderer();
    }

    public function testRenderBasicStateDiagram(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
                new State('completed', final: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
                new Transition('complete', ['paid'], 'completed'),
            ],
        );

        $output = $this->renderer->render($definition);

        $this->assertStringContainsString('flowchart TD', $output);
        $this->assertStringContainsString('pending([pending])', $output);
        $this->assertStringContainsString('paid([paid])', $output);
        $this->assertStringContainsString('completed([completed])', $output);
        $this->assertStringContainsString('pending -->|Pay| paid', $output);
        $this->assertStringContainsString('paid -->|Complete| completed', $output);
        $this->assertStringContainsString('class pending initial', $output);
        $this->assertStringContainsString('class completed final', $output);
    }

    public function testRenderWithColors(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', color: '#FFA500', initial: true),
                new State('paid', color: '#00AA00'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
            ],
        );

        $output = $this->renderer->render($definition);

        // Colors are now handled through class definitions, not inline styles
        $this->assertStringContainsString('flowchart TD', $output);
        $this->assertStringContainsString('pending([pending])', $output);
        $this->assertStringContainsString('paid([paid])', $output);
    }

    public function testRenderHappyPath(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
                new State('cancelled', final: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', happy: true),
                new Transition('cancel', ['pending'], 'cancelled'),
            ],
        );

        $output = $this->renderer->render($definition);

        // Happy path transitions get linkStyle with green stroke
        $this->assertStringContainsString('pending -->|Pay| paid', $output);
        $this->assertStringContainsString('pending -->|Cancel| cancelled', $output);
        $this->assertStringContainsString('linkStyle 0 stroke:#2e7d32,stroke-width:2px', $output);
    }

    public function testRenderWithAnalysis(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
                new State('orphan'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
            ],
        );

        $output = $this->renderer->renderWithAnalysis($definition, ['orphan']);

        $this->assertStringContainsString('classDef unreachable fill:#D3D3D3', $output);
        $this->assertStringContainsString('stroke-dasharray:5', $output);
        $this->assertStringContainsString('class orphan unreachable', $output);
    }

    public function testRenderFailedState(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('failed', failed: true),
            ],
            transitions: [
                new Transition('fail', ['pending'], 'failed'),
            ],
        );

        $output = $this->renderer->render($definition);

        $this->assertStringContainsString('classDef failed fill:#ffebee,stroke:#f44336', $output);
        $this->assertStringContainsString('class failed failed', $output);
    }

    public function testRenderWithCurrentState(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
                new State('completed', final: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
                new Transition('complete', ['paid'], 'completed'),
            ],
        );

        $output = $this->renderer->render($definition, 'paid');

        $this->assertStringContainsString('classDef current fill:#ffc107', $output);
        $this->assertStringContainsString('class paid current', $output);
    }

    public function testRenderWithMultipleFromStates(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
                new State('cancelled', final: true),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
                new Transition('cancel', ['pending', 'paid'], 'cancelled'),
            ],
        );

        $output = $this->renderer->render($definition);

        $this->assertStringContainsString('pending -->|Cancel| cancelled', $output);
        $this->assertStringContainsString('paid -->|Cancel| cancelled', $output);
    }

    public function testRenderSupportsAsciiDetailMarkers(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', false, ['ensurePayable'], ['markPaymentCaptured'], 'canAutoPay'),
            ],
        );

        $output = $this->renderer->render($definition, null, true, 'ascii');

        $this->assertStringContainsString('|Pay [G][C][?]|', $output);
    }

    public function testRenderSupportsNoDetailMarkers(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', false, ['ensurePayable']),
            ],
        );

        $output = $this->renderer->render($definition, null, true, 'none');

        $this->assertStringContainsString('|Pay|', $output);
        $this->assertStringNotContainsString('[G]', $output);
        $this->assertStringNotContainsString('🛡️', $output);
    }

    public function testRenderUsesTransitionDisplayLabel(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid', label: 'Capture payment'),
            ],
        );

        $output = $this->renderer->render($definition);

        $this->assertStringContainsString('pending -->|Capture payment| paid', $output);
        $this->assertStringNotContainsString('pending -->|pay| paid', $output);
    }
}
