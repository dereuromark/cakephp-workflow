<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Loader;

use Cake\Event\EventManager;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Workflow\Loader\AttributeLoader;
use Workflow\Service\WorkflowRegistry;

/**
 * Automatic + conditional transitions defined purely via attributes
 * (#[Transition(automatic: true)] gated by #[Condition]).
 */
class AttributeAutoTransitionTest extends TestCase
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

    public function testTransitionIsMarkedAutomaticWithCondition(): void
    {
        $definition = $this->loader->load('auto_review');
        $transition = $definition->getTransition('auto_approve');

        $this->assertTrue($transition->isAutomatic());
        $this->assertSame(
            'TestApp\\Workflow\\AutoReview\\ReviewState::isTrusted',
            $transition->getCondition(),
        );
    }

    public function testAutomaticTransitionFiresWhenConditionPasses(): void
    {
        $registry = new WorkflowRegistry($this->loader, new EventManager(), true, 10);
        $definition = $registry->getWorkflow('auto_review');
        $entity = new Entity(['state' => 'draft', 'trusted' => true]);

        $result = $registry->getEngine('auto_review')->apply($definition, $entity, 'submit');

        $this->assertTrue($result->isSuccess());
        // submit moved draft -> review, then the automatic auto_approve fired -> approved.
        $this->assertSame('approved', $entity->get('state'));
    }

    public function testAutomaticTransitionSkippedWhenConditionFails(): void
    {
        $registry = new WorkflowRegistry($this->loader, new EventManager(), true, 10);
        $definition = $registry->getWorkflow('auto_review');
        $entity = new Entity(['state' => 'draft', 'trusted' => false]);

        $result = $registry->getEngine('auto_review')->apply($definition, $entity, 'submit');

        $this->assertTrue($result->isSuccess());
        // Condition false -> stays in review.
        $this->assertSame('review', $entity->get('state'));
    }
}
