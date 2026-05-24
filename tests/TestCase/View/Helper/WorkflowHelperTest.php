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
        Router::createRouteBuilder('/')->connect('/{controller}/{action}/*');
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

    public function testPanelWithoutTransitionsRendersBadgeOnly(): void
    {
        $entity = new Entity(['id' => 7, 'state' => 'pending']);

        $panel = $this->helper->panel($this->definition, $entity, []);

        $this->assertStringContainsString('pending', $panel);
        $this->assertStringNotContainsString('<form', $panel);
    }
}
