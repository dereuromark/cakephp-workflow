<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Loader;

use PHPUnit\Framework\TestCase;
use Workflow\Exception\WorkflowException;
use Workflow\Loader\NeonLoader;

class NeonLoaderTest extends TestCase
{
    private string $tempDir;

    public function setUp(): void
    {
        parent::setUp();

        // Skip tests if nette/neon is not installed
        if (!class_exists(\Nette\Neon\Neon::class)) {
            $this->markTestSkipped('nette/neon is required for these tests');
        }

        $this->tempDir = sys_get_temp_dir() . '/workflow_neon_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        // Clean up temp files
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->tempDir);
        }
    }

    public function testSupportsReturnsFalseForNonexistentWorkflow(): void
    {
        $loader = new NeonLoader($this->tempDir);

        $this->assertFalse($loader->supports('nonexistent'));
    }

    public function testSupportsReturnsTrueForExistingNeonFile(): void
    {
        $this->createNeonFile('order', $this->getOrderWorkflowNeon());

        $loader = new NeonLoader($this->tempDir);

        $this->assertTrue($loader->supports('order'));
    }

    public function testLoadReturnsDefinition(): void
    {
        $this->createNeonFile('order', $this->getOrderWorkflowNeon());

        $loader = new NeonLoader($this->tempDir);
        $definition = $loader->load('order');

        $this->assertSame('order', $definition->getName());
        $this->assertSame('Orders', $definition->getTable());
        $this->assertSame('state', $definition->getField());
    }

    public function testLoadReturnsDefinitionWithStates(): void
    {
        $this->createNeonFile('order', $this->getOrderWorkflowNeon());

        $loader = new NeonLoader($this->tempDir);
        $definition = $loader->load('order');

        $states = $definition->getStates();
        $this->assertCount(4, $states);

        $pending = $definition->getState('pending');
        $this->assertTrue($pending->isInitial());
        $this->assertFalse($pending->isFinal());
        $this->assertSame('Pending', $pending->getLabel());

        $completed = $definition->getState('completed');
        $this->assertTrue($completed->isFinal());
        $this->assertFalse($completed->isInitial());
    }

    public function testLoadReturnsDefinitionWithTransitions(): void
    {
        $this->createNeonFile('order', $this->getOrderWorkflowNeon());

        $loader = new NeonLoader($this->tempDir);
        $definition = $loader->load('order');

        $transitions = $definition->getTransitions();
        $this->assertCount(3, $transitions);

        $pay = $definition->getTransition('pay');
        $this->assertSame(['pending'], $pay->getFrom());
        $this->assertSame('paid', $pay->getTo());
    }

    public function testLoadThrowsForNonexistentWorkflow(): void
    {
        $loader = new NeonLoader($this->tempDir);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Workflow 'nonexistent' not found in NEON files");

        $loader->load('nonexistent');
    }

    public function testLoadThrowsWhenNeonDoesNotContainWorkflow(): void
    {
        $this->createNeonFile('empty', "other:\n  table: Other\n");

        $loader = new NeonLoader($this->tempDir);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("NEON file does not contain workflow 'empty'");

        $loader->load('empty');
    }

    public function testLoadThrowsForMissingTable(): void
    {
        $neon = <<<NEON
invalid:
    field: state
    states:
        pending:
            initial: true
    transitions: {}
NEON;
        $this->createNeonFile('invalid', $neon);

        $loader = new NeonLoader($this->tempDir);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Missing 'table' in workflow 'invalid'");

        $loader->load('invalid');
    }

    public function testLoadWithDefaultField(): void
    {
        $neon = <<<NEON
simple:
    table: Items
    states:
        pending:
            initial: true
        done:
            final: true
    transitions:
        finish:
            from: pending
            to: done
NEON;
        $this->createNeonFile('simple', $neon);

        $loader = new NeonLoader($this->tempDir);
        $definition = $loader->load('simple');

        $this->assertSame('state', $definition->getField());
    }

    public function testLoadWithMetadata(): void
    {
        $neon = <<<NEON
documented:
    table: Items
    metadata:
        label: "Documented Workflow"
        description: "A workflow with documentation"
    states:
        pending:
            initial: true
    transitions: {}
NEON;
        $this->createNeonFile('documented', $neon);

        $loader = new NeonLoader($this->tempDir);
        $definition = $loader->load('documented');

        $this->assertSame('Documented Workflow', $definition->getLabel());
        $this->assertSame('A workflow with documentation', $definition->getDescription());
    }

    public function testLoadWithOnEnterOnExit(): void
    {
        $neon = <<<NEON
lifecycle:
    table: Items
    states:
        pending:
            initial: true
            onExit:
                - notifyExit
        active:
            onEnter:
                - notifyEnter
                - logEntry
    transitions:
        activate:
            from: pending
            to: active
NEON;
        $this->createNeonFile('lifecycle', $neon);

        $loader = new NeonLoader($this->tempDir);
        $definition = $loader->load('lifecycle');

        $pending = $definition->getState('pending');
        $this->assertSame(['notifyExit'], $pending->getOnExit());

        $active = $definition->getState('active');
        $this->assertSame(['notifyEnter', 'logEntry'], $active->getOnEnter());
    }

    public function testLoadWithRequireReasonFor(): void
    {
        $neon = <<<NEON
reasoned:
    table: Items
    states:
        active:
            initial: true
            requireReasonFor:
                - cancel
                - reject
        cancelled:
            final: true
        rejected:
            final: true
    transitions:
        cancel:
            from: active
            to: cancelled
        reject:
            from: active
            to: rejected
NEON;
        $this->createNeonFile('reasoned', $neon);

        $loader = new NeonLoader($this->tempDir);
        $definition = $loader->load('reasoned');

        $active = $definition->getState('active');
        $this->assertSame(['cancel', 'reject'], $active->getRequireReasonFor());
    }

    public function testLoadWithGuardAndCommand(): void
    {
        $neon = <<<NEON
guarded:
    table: Items
    states:
        pending:
            initial: true
        done:
            final: true
    transitions:
        finish:
            from: pending
            to: done
            guard: checkPermission
            command: sendNotification
NEON;
        $this->createNeonFile('guarded', $neon);

        $loader = new NeonLoader($this->tempDir);
        $definition = $loader->load('guarded');

        $transition = $definition->getTransition('finish');
        $this->assertSame(['checkPermission'], $transition->getGuards());
        $this->assertSame(['sendNotification'], $transition->getCommands());
    }

    public function testLoadCachesDefinition(): void
    {
        $this->createNeonFile('order', $this->getOrderWorkflowNeon());

        $loader = new NeonLoader($this->tempDir);
        $definition1 = $loader->load('order');
        $definition2 = $loader->load('order');

        $this->assertSame($definition1, $definition2);
    }

    public function testGetWorkflowNames(): void
    {
        $this->createNeonFile('order', $this->getOrderWorkflowNeon());
        $this->createNeonFile('payment', $this->getPaymentWorkflowNeon());

        $loader = new NeonLoader($this->tempDir);

        $names = $loader->getWorkflowNames();

        $this->assertContains('order', $names);
        $this->assertContains('payment', $names);
    }

    public function testGetWorkflowNamesReturnsEmptyForNonexistentDir(): void
    {
        $loader = new NeonLoader('/nonexistent/path');

        $names = $loader->getWorkflowNames();

        $this->assertEmpty($names);
    }

    public function testLoadWithMultipleFromStates(): void
    {
        $neon = <<<NEON
multifrom:
    table: Items
    states:
        draft:
            initial: true
        pending:
        cancelled:
            final: true
    transitions:
        cancel:
            from:
                - draft
                - pending
            to: cancelled
NEON;
        $this->createNeonFile('multifrom', $neon);

        $loader = new NeonLoader($this->tempDir);
        $definition = $loader->load('multifrom');

        $cancel = $definition->getTransition('cancel');
        $this->assertSame(['draft', 'pending'], $cancel->getFrom());
    }

    public function testLoadWithHappyPath(): void
    {
        $neon = <<<NEON
happy:
    table: Items
    states:
        pending:
            initial: true
        done:
            final: true
    transitions:
        finish:
            from: pending
            to: done
            happy: true
NEON;
        $this->createNeonFile('happy', $neon);

        $loader = new NeonLoader($this->tempDir);
        $definition = $loader->load('happy');

        $transition = $definition->getTransition('finish');
        $this->assertTrue($transition->isHappy());
    }

    public function testLoadWithColorAndFlags(): void
    {
        $neon = <<<NEON
styled:
    table: Items
    states:
        pending:
            initial: true
            color: "#FFA500"
            flags:
                - urgent
                - review
        done:
            final: true
    transitions:
        finish:
            from: pending
            to: done
NEON;
        $this->createNeonFile('styled', $neon);

        $loader = new NeonLoader($this->tempDir);
        $definition = $loader->load('styled');

        $pending = $definition->getState('pending');
        $this->assertSame('#FFA500', $pending->getColor());
        $this->assertTrue($pending->hasFlag('urgent'));
        $this->assertTrue($pending->hasFlag('review'));
        $this->assertFalse($pending->hasFlag('other'));
    }

    private function createNeonFile(string $name, string $content): void
    {
        file_put_contents($this->tempDir . '/' . $name . '.neon', $content);
    }

    private function getOrderWorkflowNeon(): string
    {
        return <<<NEON
order:
    table: Orders
    field: state
    states:
        pending:
            label: Pending
            initial: true
        paid:
            label: Paid
        shipped:
            label: Shipped
        completed:
            label: Completed
            final: true
    transitions:
        pay:
            from: pending
            to: paid
        ship:
            from: paid
            to: shipped
        complete:
            from: shipped
            to: completed
NEON;
    }

    private function getPaymentWorkflowNeon(): string
    {
        return <<<NEON
payment:
    table: Payments
    states:
        pending:
            initial: true
        processed:
            final: true
    transitions:
        process:
            from: pending
            to: processed
NEON;
    }
}
