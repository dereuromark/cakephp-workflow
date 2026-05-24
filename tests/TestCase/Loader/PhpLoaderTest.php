<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Loader;

use PHPUnit\Framework\TestCase;
use Workflow\Exception\WorkflowException;
use Workflow\Loader\PhpLoader;

class PhpLoaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wf-php-loader-' . uniqid('', true);
        mkdir($this->dir, 0775, true);
        file_put_contents($this->dir . DIRECTORY_SEPARATOR . 'order.php', <<<'PHP'
<?php
return [
    'order' => [
        'table' => 'Orders',
        'field' => 'state',
        'states' => [
            'pending' => [
                'initial' => true,
                'timeouts' => [['after' => '1 hour', 'transition' => 'pay']],
            ],
            'paid' => ['final' => true, 'color' => '#00AA00'],
        ],
        'transitions' => [
            'pay' => ['from' => 'pending', 'to' => 'paid', 'happy' => true, 'guard' => 'App\Foo::bar'],
        ],
    ],
];
PHP);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . DIRECTORY_SEPARATOR . 'order.php');
        @rmdir($this->dir);
    }

    public function testSupportsAndNames(): void
    {
        $loader = new PhpLoader($this->dir);

        $this->assertTrue($loader->supports('order'));
        $this->assertFalse($loader->supports('missing'));
        $this->assertSame(['order'], $loader->getWorkflowNames());
    }

    public function testLoadBuildsDefinition(): void
    {
        $loader = new PhpLoader($this->dir);
        $definition = $loader->load('order');

        $this->assertSame('order', $definition->getName());
        $this->assertSame('Orders', $definition->getTable());
        $this->assertSame('state', $definition->getField());
        $this->assertSame('pending', $definition->getInitialState()->getName());

        $paid = $definition->getState('paid');
        $this->assertTrue($paid->isFinal());
        $this->assertSame('#00AA00', $paid->getColor());

        $pay = $definition->getTransition('pay');
        $this->assertSame(['pending'], $pay->getFrom());
        $this->assertSame('paid', $pay->getTo());
        $this->assertSame(['App\Foo::bar'], $pay->getGuards());

        $timeouts = $definition->getState('pending')->getTimeouts();
        $this->assertCount(1, $timeouts);
        $this->assertSame('1 hour', $timeouts[0]->getAfter());
        $this->assertSame('pay', $timeouts[0]->getTransition());
    }

    public function testLoadUnknownThrows(): void
    {
        $loader = new PhpLoader($this->dir);

        $this->expectException(WorkflowException::class);
        $loader->load('missing');
    }
}
