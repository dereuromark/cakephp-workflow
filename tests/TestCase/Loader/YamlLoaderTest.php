<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Workflow\Exception\WorkflowException;
use Workflow\Loader\YamlLoader;

/**
 * @requires extension yaml
 */
class YamlLoaderTest extends TestCase
{
    private string $tempDir;

    public function setUp(): void
    {
        parent::setUp();

        // Skip tests if symfony/yaml is not installed
        if (!class_exists(Yaml::class)) {
            $this->markTestSkipped('symfony/yaml is required for these tests');
        }

        $this->tempDir = sys_get_temp_dir() . '/workflow_yaml_test_' . uniqid();
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
        $loader = new YamlLoader($this->tempDir);

        $this->assertFalse($loader->supports('nonexistent'));
    }

    public function testSupportsReturnsTrueForExistingYamlFile(): void
    {
        $this->createYamlFile('order', $this->getOrderWorkflowYaml());

        $loader = new YamlLoader($this->tempDir);

        $this->assertTrue($loader->supports('order'));
    }

    public function testSupportsReturnsTrueForYmlExtension(): void
    {
        file_put_contents($this->tempDir . '/payment.yml', $this->getPaymentWorkflowYaml());

        $loader = new YamlLoader($this->tempDir);

        $this->assertTrue($loader->supports('payment'));
    }

    public function testLoadReturnsDefinition(): void
    {
        $this->createYamlFile('order', $this->getOrderWorkflowYaml());

        $loader = new YamlLoader($this->tempDir);
        $definition = $loader->load('order');

        $this->assertSame('order', $definition->getName());
        $this->assertSame('Orders', $definition->getTable());
        $this->assertSame('state', $definition->getField());
    }

    public function testLoadReturnsDefinitionWithStates(): void
    {
        $this->createYamlFile('order', $this->getOrderWorkflowYaml());

        $loader = new YamlLoader($this->tempDir);
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
        $this->createYamlFile('order', $this->getOrderWorkflowYaml());

        $loader = new YamlLoader($this->tempDir);
        $definition = $loader->load('order');

        $transitions = $definition->getTransitions();
        $this->assertCount(3, $transitions);

        $pay = $definition->getTransition('pay');
        $this->assertSame(['pending'], $pay->getFrom());
        $this->assertSame('paid', $pay->getTo());
    }

    public function testLoadThrowsForNonexistentWorkflow(): void
    {
        $loader = new YamlLoader($this->tempDir);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Workflow 'nonexistent' not found in YAML files");

        $loader->load('nonexistent');
    }

    public function testLoadThrowsWhenYamlDoesNotContainWorkflow(): void
    {
        $this->createYamlFile('empty', "other:\n  table: Other\n");

        $loader = new YamlLoader($this->tempDir);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("YAML file does not contain workflow 'empty'");

        $loader->load('empty');
    }

    public function testLoadThrowsForMissingTable(): void
    {
        $yaml = <<<YAML
invalid:
  field: state
  states:
    pending:
      initial: true
  transitions: {}
YAML;
        $this->createYamlFile('invalid', $yaml);

        $loader = new YamlLoader($this->tempDir);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Missing 'table' in workflow 'invalid'");

        $loader->load('invalid');
    }

    public function testLoadWithDefaultField(): void
    {
        $yaml = <<<YAML
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
YAML;
        $this->createYamlFile('simple', $yaml);

        $loader = new YamlLoader($this->tempDir);
        $definition = $loader->load('simple');

        $this->assertSame('state', $definition->getField());
    }

    public function testLoadWithMetadata(): void
    {
        $yaml = <<<YAML
documented:
  table: Items
  metadata:
    label: "Documented Workflow"
    description: "A workflow with documentation"
  states:
    pending:
      initial: true
  transitions: {}
YAML;
        $this->createYamlFile('documented', $yaml);

        $loader = new YamlLoader($this->tempDir);
        $definition = $loader->load('documented');

        $this->assertSame('Documented Workflow', $definition->getLabel());
        $this->assertSame('A workflow with documentation', $definition->getDescription());
    }

    public function testLoadWithOnEnterOnExit(): void
    {
        $yaml = <<<YAML
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
YAML;
        $this->createYamlFile('lifecycle', $yaml);

        $loader = new YamlLoader($this->tempDir);
        $definition = $loader->load('lifecycle');

        $pending = $definition->getState('pending');
        $this->assertSame(['notifyExit'], $pending->getOnExit());

        $active = $definition->getState('active');
        $this->assertSame(['notifyEnter', 'logEntry'], $active->getOnEnter());
    }

    public function testLoadWithRequireReasonFor(): void
    {
        $yaml = <<<YAML
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
YAML;
        $this->createYamlFile('reasoned', $yaml);

        $loader = new YamlLoader($this->tempDir);
        $definition = $loader->load('reasoned');

        $active = $definition->getState('active');
        $this->assertSame(['cancel', 'reject'], $active->getRequireReasonFor());
    }

    public function testLoadWithGuardAndCommand(): void
    {
        $yaml = <<<YAML
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
YAML;
        $this->createYamlFile('guarded', $yaml);

        $loader = new YamlLoader($this->tempDir);
        $definition = $loader->load('guarded');

        $transition = $definition->getTransition('finish');
        $this->assertSame(['checkPermission'], $transition->getGuards());
        $this->assertSame(['sendNotification'], $transition->getCommands());
    }

    public function testLoadCachesDefinition(): void
    {
        $this->createYamlFile('order', $this->getOrderWorkflowYaml());

        $loader = new YamlLoader($this->tempDir);
        $definition1 = $loader->load('order');
        $definition2 = $loader->load('order');

        $this->assertSame($definition1, $definition2);
    }

    public function testGetWorkflowNames(): void
    {
        $this->createYamlFile('order', $this->getOrderWorkflowYaml());
        file_put_contents($this->tempDir . '/payment.yml', $this->getPaymentWorkflowYaml());

        $loader = new YamlLoader($this->tempDir);

        $names = $loader->getWorkflowNames();

        $this->assertContains('order', $names);
        $this->assertContains('payment', $names);
    }

    public function testGetWorkflowNamesReturnsEmptyForNonexistentDir(): void
    {
        $loader = new YamlLoader('/nonexistent/path');

        $names = $loader->getWorkflowNames();

        $this->assertEmpty($names);
    }

    public function testLoadWithMultipleFromStates(): void
    {
        $yaml = <<<YAML
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
YAML;
        $this->createYamlFile('multifrom', $yaml);

        $loader = new YamlLoader($this->tempDir);
        $definition = $loader->load('multifrom');

        $cancel = $definition->getTransition('cancel');
        $this->assertSame(['draft', 'pending'], $cancel->getFrom());
    }

    public function testLoadWithHappyPath(): void
    {
        $yaml = <<<YAML
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
YAML;
        $this->createYamlFile('happy', $yaml);

        $loader = new YamlLoader($this->tempDir);
        $definition = $loader->load('happy');

        $transition = $definition->getTransition('finish');
        $this->assertTrue($transition->isHappy());
    }

    public function testLoadWithStateTimeouts(): void
    {
        $yaml = <<<YAML
timed:
  table: Orders
  states:
    pending:
      initial: true
      timeouts:
        - after: PT30M
          transition: cancel
        - after: 2 hours
          transition: remind
    cancelled:
      final: true
    reminded:
  transitions:
    cancel:
      from: pending
      to: cancelled
    remind:
      from: pending
      to: reminded
YAML;
        $this->createYamlFile('timed', $yaml);

        $loader = new YamlLoader($this->tempDir);
        $definition = $loader->load('timed');

        $pending = $definition->getState('pending');
        $this->assertCount(2, $pending->getTimeouts());
        $this->assertSame('PT30M', $pending->getTimeouts()[0]->getAfter());
        $this->assertSame('cancel', $pending->getTimeouts()[0]->getTransition());
        $this->assertSame('2 hours', $pending->getTimeouts()[1]->getAfter());
        $this->assertSame('remind', $pending->getTimeouts()[1]->getTransition());
    }

    private function createYamlFile(string $name, string $content): void
    {
        file_put_contents($this->tempDir . '/' . $name . '.yaml', $content);
    }

    private function getOrderWorkflowYaml(): string
    {
        return <<<YAML
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
YAML;
    }

    private function getPaymentWorkflowYaml(): string
    {
        return <<<YAML
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
YAML;
    }
}
