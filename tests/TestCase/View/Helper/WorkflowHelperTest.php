<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\View\Helper;

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
        parent::setUp();
        $this->helper = new WorkflowHelper(new View());
        $this->definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true, color: '#FFA500'),
                new State('paid', label: 'Paid'),
            ],
            transitions: [
                new Transition('pay', ['pending'], 'paid'),
            ],
        );
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
}
