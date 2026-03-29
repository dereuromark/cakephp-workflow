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
}
