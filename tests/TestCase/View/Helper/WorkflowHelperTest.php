<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\View\Helper;

use Cake\ORM\Entity;
use Cake\Routing\Router;
use Cake\View\View;
use PHPUnit\Framework\TestCase;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\View\Helper\WorkflowHelper;

class WorkflowHelperTest extends TestCase
{
    private WorkflowHelper $helper;

    private Definition $definition;

    protected function setUp(): void
    {
        Router::reload();
        $routes = Router::createRouteBuilder('/');
        $routes->connect('/{controller}/{action}/*');
        $routes->plugin('Workflow', function ($routes): void {
            $routes->fallbacks();
        });
        $routes->prefix('Admin', function ($routes): void {
            $routes->plugin('Workflow', function ($routes): void {
                $routes->fallbacks();
            });
        });
        $this->helper = new WorkflowHelper(new View());
        $this->definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', color: '#FFA500', initial: true),
                new State('paid', label: 'Paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
            ],
        );
    }

    protected function tearDown(): void
    {
        Router::reload();
    }

    public function testStateBadgeRendersKnownState(): void
    {
        $badge = $this->helper->stateBadge($this->definition, 'paid');

        $this->assertStringContainsString('Paid', $badge);
    }

    public function testStateBadgeRendersOrphanedStateWithoutThrowing(): void
    {
        $badge = $this->helper->stateBadge($this->definition, 'ghost');

        $this->assertStringContainsString('ghost', $badge);
    }

    public function testGetStateColorReturnsDefaultForOrphanedState(): void
    {
        $color = $this->helper->getStateColor($this->definition, 'ghost');

        $this->assertSame('#6c757d', $color);
    }

    public function testPanelRendersBadgeAndTransitionForms(): void
    {
        $entity = new Entity(['id' => 7, 'state' => 'pending']);

        $panel = $this->helper->panel($this->definition, $entity, ['pay'], [
            'url' => ['controller' => 'Orders', 'action' => 'transition'],
        ]);

        $this->assertStringContainsString('workflow-panel', $panel);
        $this->assertStringContainsString('pending', $panel);
        $this->assertStringContainsString('<form', $panel);
        $this->assertStringContainsString('Pay', $panel);
        $this->assertStringContainsString('data-transition="pay"', $panel);
        // The POST carries the transition name and targets the entity id.
        $this->assertMatchesRegularExpression('/name="transition"[^>]*value="pay"/', $panel);
        $this->assertStringContainsString('/Orders/transition/7/pay', $panel);
    }

    public function testIncludeMermaidCanBeConfigured(): void
    {
        $script = $this->helper->includeMermaid([
            'src' => 'https://example.com/mermaid.js',
            'startOnLoad' => false,
            'config' => ['theme' => 'neutral'],
            'guardKey' => '__customMermaidGuard',
        ]);

        $this->assertStringContainsString('https://example.com/mermaid.js', $script);
        $this->assertStringContainsString('__customMermaidGuard', $script);
        $this->assertStringContainsString('"startOnLoad":false', $script);
        $this->assertStringContainsString('"theme":"neutral"', $script);
    }

    public function testIncludeMermaidUsesIdempotentGuardByDefault(): void
    {
        $script = $this->helper->includeMermaid();

        $this->assertStringContainsString('__workflowMermaidInitialized', $script);
        $this->assertStringContainsString('mermaid.initialize', $script);
    }

    public function testDiagramSupportsCurrentStateAndDetailedLabels(): void
    {
        $diagram = $this->helper->diagram($this->definition, [
            'currentState' => 'paid',
            'showDetails' => true,
        ]);

        $this->assertStringContainsString('class paid current', $diagram);
        $this->assertStringContainsString('|pay|', $diagram);
    }

    public function testDiagramSupportsCodeMode(): void
    {
        $diagram = $this->helper->diagram($this->definition, ['mode' => 'code']);

        $this->assertStringContainsString('<pre', $diagram);
        $this->assertStringContainsString('flowchart TD', $diagram);
    }

    public function testGetMermaidCodeSupportsOptions(): void
    {
        $diagram = $this->helper->getMermaidCode($this->definition, [
            'currentState' => 'paid',
            'showDetails' => true,
        ]);

        $this->assertStringContainsString('class paid current', $diagram);
        $this->assertStringContainsString('|pay|', $diagram);
    }

    public function testDiagramDataReturnsEmbeddingMetadata(): void
    {
        $data = $this->helper->diagramData($this->definition, ['currentState' => 'paid']);

        $this->assertSame('paid', $data['currentState']);
        $this->assertSame('Paid', $data['currentStateLabel']);
        $this->assertStringContainsString('flowchart TD', $data['mermaid']);
    }

    public function testWidgetRendersPreviewControlsAndModal(): void
    {
        $widget = $this->helper->widget($this->definition, [
            'id' => 'order-workflow',
            'title' => 'Order workflow',
            'currentState' => 'paid',
            'export' => ['svg', 'png', 'mmd'],
            'showDetails' => true,
            'detailMarkers' => 'ascii',
        ]);

        $this->assertStringContainsString('data-workflow-render-root="order-workflow"', $widget);
        $this->assertStringContainsString('data-workflow-toggle-code="order-workflow"', $widget);
        $this->assertStringContainsString('data-workflow-export-svg="order-workflow"', $widget);
        $this->assertStringContainsString('data-workflow-export-png="order-workflow"', $widget);
        $this->assertStringContainsString('data-workflow-export-mmd="order-workflow"', $widget);
        $this->assertStringContainsString('Export Mermaid', $widget);
        $this->assertStringContainsString('data-bs-target="#order-workflow-modal"', $widget);
        $this->assertStringContainsString('Current state: <strong>Paid</strong>', $widget);
    }

    public function testIncludeMermaidToolkitCanBeEnabled(): void
    {
        $script = $this->helper->includeMermaid(['toolkit' => true]);

        $this->assertStringContainsString('__workflowMermaidToolkitInitialized', $script);
        $this->assertStringContainsString('data-workflow-render-root', $script);
        $this->assertStringContainsString('renderRoot', $script);
        $this->assertStringContainsString('data-workflow-export-png', $script);
        $this->assertStringContainsString('canvas.toBlob', $script);
        $this->assertStringContainsString('foreignObject', $script);
        $this->assertStringContainsString('DOMParser', $script);
    }

    public function testWidgetCanExportByEntityId(): void
    {
        $widget = $this->helper->widget($this->definition, [
            'id' => 'order-workflow',
            'export' => 'svg',
            'entityId' => 42,
        ]);

        $this->assertStringContainsString('data-workflow-export-svg="order-workflow"', $widget);
    }

    public function testWidgetCanTargetAdminExportScope(): void
    {
        $widget = $this->helper->widget($this->definition, [
            'id' => 'order-workflow',
            'export' => 'svg',
            'exportScope' => 'admin',
        ]);

        $this->assertStringContainsString('data-workflow-export-svg="order-workflow"', $widget);
    }

    public function testPanelWithoutTransitionsRendersBadgeOnly(): void
    {
        $entity = new Entity(['id' => 7, 'state' => 'pending']);

        $panel = $this->helper->panel($this->definition, $entity, []);

        $this->assertStringContainsString('pending', $panel);
        $this->assertStringNotContainsString('<form', $panel);
    }
}
