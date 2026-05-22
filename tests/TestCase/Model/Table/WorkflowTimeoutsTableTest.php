<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\Locator\LocatorAwareTrait;
use Workflow\Model\Table\WorkflowTimeoutsTable;
use Workflow\Test\TestCase\DatabaseTestCase;

class WorkflowTimeoutsTableTest extends DatabaseTestCase
{
    use LocatorAwareTrait;

    private WorkflowTimeoutsTable $timeouts;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();
        /** @var \Workflow\Model\Table\WorkflowTimeoutsTable $timeouts */
        $timeouts = $this->fetchTable('Workflow.WorkflowTimeouts');
        $this->timeouts = $timeouts;
    }

    public function testFindDueComparesInUtcRegardlessOfAppTimezone(): void
    {
        $original = date_default_timezone_get();
        // A non-UTC app timezone (UTC+9). due_at is stored in UTC, so the runner must
        // compare in UTC; comparing in local time would fire the 1h-future timeout early.
        date_default_timezone_set('Asia/Tokyo');

        try {
            $this->insertTimeout('future', gmdate('Y-m-d H:i:s', time() + 3600));
            $this->insertTimeout('past', gmdate('Y-m-d H:i:s', time() - 3600));

            /** @var array<\Workflow\Model\Entity\WorkflowTimeout> $due */
            $due = $this->timeouts->find('due')->all()->toArray();

            $this->assertCount(1, $due);
            $this->assertSame('past', $due[0]->get('current_state'));
        } finally {
            date_default_timezone_set($original);
        }
    }

    private function insertTimeout(string $marker, string $dueAtUtc): void
    {
        ConnectionManager::get('test')->insert('workflow_timeouts', [
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => 1,
            'current_state' => $marker,
            'transition_name' => 'expire',
            'due_at' => $dueAtUtc,
            'processed' => 0,
            'created' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
