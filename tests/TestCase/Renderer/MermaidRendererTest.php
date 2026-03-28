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
        parent::setUp();
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

        $this->assertStringContainsString('stateDiagram-v2', $output);
        $this->assertStringContainsString('[*] --> pending', $output);
        $this->assertStringContainsString('pending --> paid: pay', $output);
        $this->assertStringContainsString('paid --> completed: complete', $output);
        $this->assertStringContainsString('completed --> [*]', $output);
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

        $this->assertStringContainsString('style pending fill:#FFA500', $output);
        $this->assertStringContainsString('style paid fill:#00AA00', $output);
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

        // Happy path transitions should be thicker/styled differently
        $this->assertStringContainsString('pending ==> paid: pay', $output);
        $this->assertStringContainsString('pending --> cancelled: cancel', $output);
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

        $this->assertStringContainsString('style orphan fill:#D3D3D3', $output);
        $this->assertStringContainsString('stroke-dasharray:5', $output);
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

        $this->assertStringContainsString('style failed fill:#FF6B6B', $output);
        $this->assertStringContainsString('stroke:#CC0000', $output);
    }
}
