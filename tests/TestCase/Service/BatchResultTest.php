<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Service;

use Cake\ORM\Entity;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Workflow\Engine\TransitionResult;
use Workflow\Service\BatchResult;

class BatchResultTest extends TestCase
{
    public function testEmptyResult(): void
    {
        $result = new BatchResult();

        $this->assertSame(0, $result->getTotal());
        $this->assertSame(0, $result->getSuccessCount());
        $this->assertSame(0, $result->getFailureCount());
        $this->assertFalse($result->isFullSuccess());
        $this->assertFalse($result->hasSuccesses());
        $this->assertFalse($result->hasFailures());
    }

    public function testAddResults(): void
    {
        $result = new BatchResult();

        $entity1 = new Entity(['id' => '1']);
        $entity2 = new Entity(['id' => '2']);

        $result->add($entity1, TransitionResult::success('pending', 'paid'));
        $result->add($entity2, TransitionResult::blocked('pending', ['guard' => 'denied']));

        $this->assertSame(2, $result->getTotal());
        $this->assertSame(1, $result->getSuccessCount());
        $this->assertSame(1, $result->getFailureCount());
    }

    public function testFullSuccess(): void
    {
        $result = new BatchResult();

        $entity1 = new Entity(['id' => '1']);
        $entity2 = new Entity(['id' => '2']);

        $result->add($entity1, TransitionResult::success('pending', 'paid'));
        $result->add($entity2, TransitionResult::success('pending', 'paid'));

        $this->assertTrue($result->isFullSuccess());
        $this->assertTrue($result->hasSuccesses());
        $this->assertFalse($result->hasFailures());
    }

    public function testAllFailures(): void
    {
        $result = new BatchResult();

        $entity1 = new Entity(['id' => '1']);
        $entity2 = new Entity(['id' => '2']);

        $result->add($entity1, TransitionResult::blocked('pending', ['x' => 'y']));
        $result->add($entity2, TransitionResult::locked('pending'));

        $this->assertFalse($result->isFullSuccess());
        $this->assertFalse($result->hasSuccesses());
        $this->assertTrue($result->hasFailures());
        $this->assertSame(2, $result->getFailureCount());
    }

    public function testGetSuccesses(): void
    {
        $result = new BatchResult();

        $entity1 = new Entity(['id' => '1']);
        $entity2 = new Entity(['id' => '2']);
        $entity3 = new Entity(['id' => '3']);

        $result->add($entity1, TransitionResult::success('pending', 'paid'));
        $result->add($entity2, TransitionResult::blocked('pending', ['x' => 'y']));
        $result->add($entity3, TransitionResult::success('pending', 'paid'));

        $successes = $result->getSuccesses();
        $this->assertCount(2, $successes);
        $this->assertSame('1', $successes[0]['entity']->get('id'));
        $this->assertSame('3', $successes[1]['entity']->get('id'));
    }

    public function testGetFailures(): void
    {
        $result = new BatchResult();

        $entity1 = new Entity(['id' => '1']);
        $entity2 = new Entity(['id' => '2']);
        $entity3 = new Entity(['id' => '3']);

        $result->add($entity1, TransitionResult::success('pending', 'paid'));
        $result->add($entity2, TransitionResult::blocked('pending', ['x' => 'y']));
        $result->add($entity3, TransitionResult::error('pending', new RuntimeException('fail')));

        $failures = $result->getFailures();
        $this->assertCount(2, $failures);
        $this->assertSame('2', $failures[0]['entity']->get('id'));
        $this->assertSame('3', $failures[1]['entity']->get('id'));
    }

    public function testGetSuccessfulEntities(): void
    {
        $result = new BatchResult();

        $entity1 = new Entity(['id' => '1']);
        $entity2 = new Entity(['id' => '2']);

        $result->add($entity1, TransitionResult::success('pending', 'paid'));
        $result->add($entity2, TransitionResult::blocked('pending', ['x' => 'y']));

        $entities = $result->getSuccessfulEntities();
        $this->assertCount(1, $entities);
        $this->assertSame('1', $entities[0]->get('id'));
    }

    public function testGetFailedEntities(): void
    {
        $result = new BatchResult();

        $entity1 = new Entity(['id' => '1']);
        $entity2 = new Entity(['id' => '2']);

        $result->add($entity1, TransitionResult::success('pending', 'paid'));
        $result->add($entity2, TransitionResult::blocked('pending', ['x' => 'y']));

        $entities = $result->getFailedEntities();
        $this->assertCount(1, $entities);
        $this->assertSame('2', $entities[0]->get('id'));
    }

    public function testConstructorWithResults(): void
    {
        $entity = new Entity(['id' => '1']);
        $transitionResult = TransitionResult::success('pending', 'paid');

        $result = new BatchResult([
            ['entity' => $entity, 'result' => $transitionResult],
        ]);

        $this->assertSame(1, $result->getTotal());
        $this->assertSame(1, $result->getSuccessCount());
    }
}
