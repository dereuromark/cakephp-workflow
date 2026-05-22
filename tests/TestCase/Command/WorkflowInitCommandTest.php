<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

class WorkflowInitCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    private string $tmpDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'workflow-init-' . uniqid('', true);
        mkdir($this->tmpDir, 0775, true);
    }

    public function tearDown(): void
    {
        $this->deleteDirectory($this->tmpDir);

        parent::tearDown();
    }

    public function testExecuteGeneratesWorkflowFiles(): void
    {
        $namespace = 'TestApp\\Workflow';
        $this->exec(sprintf(
            'workflow init order Orders --namespace %s --path %s',
            escapeshellarg($namespace),
            escapeshellarg($this->tmpDir),
        ));

        $this->assertExitSuccess();
        $this->assertOutputContains('Workflow name: order');

        $basePath = $this->tmpDir . DIRECTORY_SEPARATOR . 'Order';
        $this->assertFileExists($basePath . DIRECTORY_SEPARATOR . 'BaseOrderState.php');
        $this->assertFileExists($basePath . DIRECTORY_SEPARATOR . 'PendingState.php');
        $this->assertFileExists($basePath . DIRECTORY_SEPARATOR . 'CompletedState.php');

        $this->assertStringContainsString(
            "#[StateMachine(name: 'order', table: 'Orders', field: 'state')]",
            (string)file_get_contents($basePath . DIRECTORY_SEPARATOR . 'BaseOrderState.php'),
        );
        $this->assertStringContainsString(
            "#[Transition(to: CompletedState::class, name: 'completed', happy: true)]",
            (string)file_get_contents($basePath . DIRECTORY_SEPARATOR . 'PendingState.php'),
        );
    }

    public function testExecuteWithMigrationScaffoldsStateColumnMigration(): void
    {
        $migrationsDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'Migrations';
        mkdir($migrationsDir, 0775, true);

        $this->exec(sprintf(
            'workflow init order Orders --namespace %s --path %s --migration --migrations-path %s',
            escapeshellarg('TestApp\\Workflow'),
            escapeshellarg($this->tmpDir),
            escapeshellarg($migrationsDir),
        ));

        $this->assertExitSuccess();

        $migrations = glob($migrationsDir . DIRECTORY_SEPARATOR . '*_AddStateColumnToOrders.php') ?: [];
        $this->assertCount(1, $migrations);

        $contents = (string)file_get_contents($migrations[0]);
        $this->assertStringContainsString('class AddStateColumnToOrders extends BaseMigration', $contents);
        $this->assertStringContainsString("\$this->table('orders')", $contents);
        $this->assertStringContainsString("->addColumn('state', 'string'", $contents);
        $this->assertStringContainsString("->addIndex(['state'])", $contents);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);

                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
