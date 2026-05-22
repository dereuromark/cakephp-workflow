<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
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

    public function testFindDueIsTimezoneConsistent(): void
    {
        $original = date_default_timezone_get();
        // A non-UTC app timezone (UTC+9). due_at is written and compared on the same
        // basis (DateTime::now(), app timezone), so the runner must stay self-consistent
        // here. A read that switched to UTC would skew the comparison by the offset.
        date_default_timezone_set('Asia/Tokyo');

        try {
            $this->insertTimeout('future', DateTime::now()->addHours(1));
            $this->insertTimeout('past', DateTime::now()->subHours(1));

            /** @var array<\Workflow\Model\Entity\WorkflowTimeout> $due */
            $due = $this->timeouts->find('due')->all()->toArray();

            $this->assertCount(1, $due);
            $this->assertSame('past', $due[0]->get('current_state'));
        } finally {
            date_default_timezone_set($original);
        }
    }

    private function insertTimeout(string $marker, DateTime $dueAt): void
    {
        ConnectionManager::get('test')->insert('workflow_timeouts', [
            'workflow_name' => 'order',
            'entity_table' => 'Orders',
            'entity_id' => 1,
            'current_state' => $marker,
            'transition_name' => 'expire',
            'due_at' => $dueAt->format('Y-m-d H:i:s'),
            'processed' => 0,
            'created' => $dueAt->format('Y-m-d H:i:s'),
        ]);
    }
}
