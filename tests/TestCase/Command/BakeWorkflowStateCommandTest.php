<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Command;

use Bake\Command\SimpleBakeCommand;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

class BakeWorkflowStateCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    private string $workflowPath;

    private string $testPath;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists(SimpleBakeCommand::class)) {
            $this->markTestSkipped('cakephp/bake is not installed.');
        }

        $this->workflowPath = APP . 'Workflow' . DIRECTORY_SEPARATOR . 'Order' . DIRECTORY_SEPARATOR;
        $this->testPath = ROOT . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'TestCase' . DIRECTORY_SEPARATOR . 'Workflow' . DIRECTORY_SEPARATOR . 'Order' . DIRECTORY_SEPARATOR;

        $this->removeFiles();
    }

    public function tearDown(): void
    {
        $this->removeFiles();

        parent::tearDown();
    }

    public function testExecute(): void
    {
        $this->exec('bake workflow_state Order/Shipped --transition-to delivered --transition-name deliver --final -f');

        $this->assertExitSuccess();
        $this->assertOutputContains('Creating file');
        $this->assertOutputContains('ShippedState.php');

        $file = $this->workflowPath . 'ShippedState.php';
        $this->assertFileExists($file);
        $this->assertStringContainsString('namespace TestApp\\Workflow\\Order;', (string)file_get_contents($file));
        $this->assertStringContainsString('#[FinalState]', (string)file_get_contents($file));
        $this->assertStringContainsString("#[Transition(to: DeliveredState::class, name: 'deliver')]", (string)file_get_contents($file));

        $testFile = $this->testPath . 'ShippedStateTest.php';
        $this->assertFileExists($testFile);
        $this->assertStringContainsString('class ShippedStateTest extends TestCase', (string)file_get_contents($testFile));
    }

    public function testExecuteRejectsHalfTransitionOptions(): void
    {
        $this->exec('bake workflow_state Order/Shipped --transition-to delivered');

        $this->assertExitError();
        $this->assertErrorContains('Use --transition-to and --transition-name together.');
    }

    private function removeFiles(): void
    {
        $files = [
            $this->workflowPath . 'ShippedState.php',
            $this->testPath . 'ShippedStateTest.php',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
