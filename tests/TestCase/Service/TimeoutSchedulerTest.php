<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Service;

use Cake\ORM\Entity;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\StateTimeout;
use Workflow\Exception\WorkflowException;
use Workflow\Service\TimeoutScheduler;
use Workflow\Test\TestCase\DatabaseTestCase;

class TimeoutSchedulerTest extends DatabaseTestCase
{
    private TimeoutScheduler $scheduler;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();
        $this->scheduler = new TimeoutScheduler();
    }

    public function testSyncStateTimeoutsReplacesPendingTimeoutsForEntity(): void
    {
        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $timeoutsTable->saveOrFail($timeoutsTable->newEntity([
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => '123',
            'current_state' => 'pending',
            'transition_name' => 'cancel',
            'due_at' => '2026-03-29 10:00:00',
            'processed' => false,
        ]));

        $entity = new Entity(['id' => '123']);
        $state = new State('paid', timeouts: [
            new StateTimeout('PT1H', 'ship'),
            new StateTimeout('2 hours', 'remind'),
        ]);

        $this->scheduler->syncStateTimeouts('order', 'Orders', $entity, $state);

        $timeouts = $timeoutsTable->find()
            ->orderByAsc('id')
            ->toArray();

        $this->assertCount(3, $timeouts);
        $this->assertTrue($timeouts[0]->processed);
        $this->assertSame('cancel', $timeouts[0]->transition_name);
        $this->assertFalse($timeouts[1]->processed);
        $this->assertSame('paid', $timeouts[1]->current_state);
        $this->assertSame('ship', $timeouts[1]->transition_name);
        $this->assertFalse($timeouts[2]->processed);
        $this->assertSame('remind', $timeouts[2]->transition_name);
    }

    public function testSyncStateTimeoutsThrowsForInvalidDuration(): void
    {
        $entity = new Entity(['id' => '123']);
        $state = new State('paid', timeouts: [
            new StateTimeout('not a duration', 'ship'),
        ]);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('Invalid timeout duration');

        $this->scheduler->syncStateTimeouts('order', 'Orders', $entity, $state);
    }
}
