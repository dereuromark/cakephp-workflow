<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Loader;

use Cake\Event\EventManager;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Workflow\Loader\AttributeLoader;
use Workflow\Service\WorkflowRegistry;

class AttributeLoaderTest extends TestCase
{
    private AttributeLoader $loader;

    public function setUp(): void
    {
        parent::setUp();
        $this->loader = new AttributeLoader(
            ['TestApp\\Workflow'],
            ['TestApp\\' => TESTS . 'test_app' . DS . 'src' . DS],
        );
    }

    public function testLoadAttributeWorkflowExecutesRuntimeHandlers(): void
    {
        $registry = new WorkflowRegistry($this->loader, new EventManager(), true, 10);
        $definition = $registry->getWorkflow('attribute_order');
        $entity = new Entity(['state' => 'pending', 'total' => 10]);

        $result = $registry->getEngine('attribute_order')->apply($definition, $entity, 'pay');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('paid', $entity->get('state'));
        $this->assertTrue($entity->get('command_ran'));
        $this->assertTrue($entity->get('entered_paid'));
    }

    public function testLoadAttributeWorkflowEvaluatesGuards(): void
    {
        $registry = new WorkflowRegistry($this->loader, new EventManager(), true, 10);
        $definition = $registry->getWorkflow('attribute_order');
        $entity = new Entity(['state' => 'pending', 'total' => 0]);

        $result = $registry->getEngine('attribute_order')->apply($definition, $entity, 'pay');

        $this->assertTrue($result->isBlocked());
        $this->assertSame('pending', $entity->get('state'));
        $this->assertSame(
            'Total must be positive',
            $result->getBlockedBy()['TestApp\\Workflow\\Order\\PendingState::ensurePayable'],
        );
    }

    public function testLoadAttributeWorkflowIncludesStateTimeouts(): void
    {
        $definition = $this->loader->load('attribute_order');
        $pending = $definition->getState('pending');

        $this->assertTrue($pending->hasTimeouts());
        $this->assertCount(1, $pending->getTimeouts());
        $this->assertSame('PT1H', $pending->getTimeouts()[0]->getAfter());
        $this->assertSame('pay', $pending->getTimeouts()[0]->getTransition());
    }

    public function testLoadAttributeWorkflowIncludesTransitionLabel(): void
    {
        $definition = $this->loader->load('attribute_order');

        $transition = $definition->getTransition('pay');

        $this->assertSame('Capture payment', $transition->getDisplayName());
    }
}
