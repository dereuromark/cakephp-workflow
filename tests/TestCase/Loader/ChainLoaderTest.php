<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Loader;

use PHPUnit\Framework\TestCase;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Exception\WorkflowException;
use Workflow\Loader\ChainLoader;
use Workflow\Loader\LoaderInterface;

class ChainLoaderTest extends TestCase
{
    public function testSupportsReturnsFalseWhenNoLoaders(): void
    {
        $loader = new ChainLoader([]);

        $this->assertFalse($loader->supports('any'));
    }

    public function testSupportsReturnsTrueWhenLoaderSupports(): void
    {
        $mockLoader = $this->createMock(LoaderInterface::class);
        $mockLoader->method('supports')->willReturnCallback(function ($name) {
            return $name === 'order';
        });

        $loader = new ChainLoader([$mockLoader]);

        $this->assertTrue($loader->supports('order'));
        $this->assertFalse($loader->supports('other'));
    }

    public function testSupportsChecksLoadersInOrder(): void
    {
        $loader1 = $this->createMock(LoaderInterface::class);
        $loader1->method('supports')->willReturn(false);

        $loader2 = $this->createMock(LoaderInterface::class);
        $loader2->method('supports')->willReturn(true);

        $chainLoader = new ChainLoader([$loader1, $loader2]);

        $this->assertTrue($chainLoader->supports('any'));
    }

    public function testLoadReturnsDefinitionFromFirstSupportingLoader(): void
    {
        $definition = $this->createTestDefinition('order');

        $loader1 = $this->createMock(LoaderInterface::class);
        $loader1->method('supports')->willReturn(false);

        $loader2 = $this->createMock(LoaderInterface::class);
        $loader2->method('supports')->willReturn(true);
        $loader2->method('load')->willReturn($definition);

        $chainLoader = new ChainLoader([$loader1, $loader2]);

        $result = $chainLoader->load('order');

        $this->assertSame($definition, $result);
    }

    public function testLoadThrowsWhenNoLoaderSupports(): void
    {
        $loader = new ChainLoader([]);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage("Workflow 'order' not found in any loader");

        $loader->load('order');
    }

    public function testGetWorkflowNamesAggregatesFromAllLoaders(): void
    {
        $loader1 = $this->createMock(LoaderInterface::class);
        $loader1->method('getWorkflowNames')->willReturn(['order', 'payment']);

        $loader2 = $this->createMock(LoaderInterface::class);
        $loader2->method('getWorkflowNames')->willReturn(['shipping', 'order']);

        $chainLoader = new ChainLoader([$loader1, $loader2]);

        $names = $chainLoader->getWorkflowNames();

        $this->assertContains('order', $names);
        $this->assertContains('payment', $names);
        $this->assertContains('shipping', $names);
        // 'order' should only appear once
        $this->assertCount(3, $names);
    }

    public function testAddLoader(): void
    {
        $definition = $this->createTestDefinition('order');

        $loader1 = $this->createMock(LoaderInterface::class);
        $loader1->method('supports')->willReturn(false);
        $loader1->method('getWorkflowNames')->willReturn([]);

        $loader2 = $this->createMock(LoaderInterface::class);
        $loader2->method('supports')->willReturn(true);
        $loader2->method('load')->willReturn($definition);
        $loader2->method('getWorkflowNames')->willReturn(['order']);

        $chainLoader = new ChainLoader([$loader1]);
        $this->assertFalse($chainLoader->supports('order'));

        $chainLoader->addLoader($loader2);
        $this->assertTrue($chainLoader->supports('order'));
    }

    public function testLoadPriorityFirstLoaderWins(): void
    {
        $definition1 = $this->createTestDefinition('order');
        $definition2 = $this->createTestDefinition('order');

        $loader1 = $this->createMock(LoaderInterface::class);
        $loader1->method('supports')->willReturn(true);
        $loader1->method('load')->willReturn($definition1);

        $loader2 = $this->createMock(LoaderInterface::class);
        $loader2->method('supports')->willReturn(true);
        $loader2->method('load')->willReturn($definition2);

        $chainLoader = new ChainLoader([$loader1, $loader2]);

        $result = $chainLoader->load('order');

        $this->assertSame($definition1, $result);
    }

    private function createTestDefinition(string $name): Definition
    {
        return new Definition(
            name: $name,
            table: 'Orders',
            field: 'state',
            states: [
                new State('pending', initial: true),
                new State('completed', final: true),
            ],
            transitions: [
                new Transition('complete', ['pending'], 'completed'),
            ],
        );
    }
}
