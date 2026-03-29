<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Engine;

use PHPUnit\Framework\TestCase;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Engine\WorkflowAnalyzer;

class WorkflowAnalyzerTest extends TestCase
{
    public function testAnalyzeReportsOutgoingTransitionFromFinalState(): void
    {
        $definition = new Definition(
            name: 'order',
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('delivered', final: true),
                new State('refunded', final: true),
            ],
            transitions: [
                new Transition('deliver', ['pending'], 'delivered', happy: true),
                new Transition('refund', ['delivered'], 'refunded'),
            ],
        );

        $analyzer = new WorkflowAnalyzer();
        $issues = $analyzer->analyze($definition);

        $terminalIssues = array_values(array_filter(
            $issues,
            fn (array $issue): bool => $issue['type'] === 'terminal_state_outgoing_transition',
        ));

        $this->assertCount(1, $terminalIssues);
        $this->assertSame('error', $terminalIssues[0]['severity']);
        $this->assertStringContainsString("Transition 'refund' starts from final state 'delivered'", $terminalIssues[0]['message']);
    }

    public function testAnalyzeReportsOutgoingTransitionFromFailedState(): void
    {
        $definition = new Definition(
            name: 'document',
            table: 'Documents',
            field: 'state',
            states: [
                new State('draft', initial: true),
                new State('rejected', failed: true),
            ],
            transitions: [
                new Transition('reject', ['draft'], 'rejected'),
                new Transition('revise', ['rejected'], 'draft'),
            ],
        );

        $analyzer = new WorkflowAnalyzer();
        $issues = $analyzer->analyze($definition);

        $terminalIssues = array_values(array_filter(
            $issues,
            fn (array $issue): bool => $issue['type'] === 'terminal_state_outgoing_transition',
        ));

        $this->assertCount(1, $terminalIssues);
        $this->assertSame('failed', $terminalIssues[0]['context']['stateType']);
        $this->assertStringContainsString("Transition 'revise' starts from failed state 'rejected'", $terminalIssues[0]['message']);
    }
}
