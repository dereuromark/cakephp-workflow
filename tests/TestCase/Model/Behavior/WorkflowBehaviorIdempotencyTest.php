<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Model\Behavior;

use Cake\Datasource\ConnectionManager;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Workflow\Engine\Definition\Definition;
use Workflow\Engine\Definition\State;
use Workflow\Engine\Definition\Transition;
use Workflow\Loader\LoaderInterface;
use Workflow\Model\Behavior\WorkflowBehavior;
use Workflow\Service\WorkflowRegistry;
use Workflow\Test\TestCase\DatabaseTestCase;

/**
 * The idempotency-key check is evaluated inside the lock so that, under
 * concurrency, two callers with the same key cannot both pass it. A self-loop
 * transition is used so the second attempt is rejected by idempotency and not
 * merely by the state guard.
 */
#[AllowMockObjectsWithoutExpectations]
class WorkflowBehaviorIdempotencyTest extends DatabaseTestCase
{
    private Table $ticketsTable;

    public function setUp(): void
    {
        parent::setUp();
        $this->truncateTables();

        $connection = ConnectionManager::get('test');
        if (!in_array('tickets', $connection->getSchemaCollection()->listTables(), true)) {
            $connection->execute('
                CREATE TABLE tickets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    state VARCHAR(64) NOT NULL,
                    created DATETIME,
                    modified DATETIME
                )
            ');
        }
        $connection->execute('DELETE FROM tickets');

        $this->ticketsTable = new Table([
            'table' => 'tickets',
            'alias' => 'Tickets',
            'connection' => $connection,
        ]);
        $this->ticketsTable->setPrimaryKey('id');
        $this->ticketsTable->addBehavior('Workflow', [
            'className' => WorkflowBehavior::class,
            'workflow' => 'ticket',
            'registry' => $this->createMockRegistry(),
            'useLocking' => true,
        ]);
    }

    public function testSecondTransitionWithSameIdempotencyKeyIsBlocked(): void
    {
        $ticket = $this->ticketsTable->newEntity(['state' => 'open']);
        $this->ticketsTable->saveOrFail($ticket);

        $options = ['log' => true, 'lock' => true];

        // First application of the self-loop transition with the key succeeds.
        $first = $this->ticketsTable->getBehavior('Workflow')
            ->transition($ticket, 'touch', ['_idempotency_key' => 'KEY-1'], $options);
        $this->assertTrue($first->isSuccess());

        // A fresh handle replays the same transition with the same key. The state
        // still allows 'touch' (self-loop), so only the idempotency check can stop it.
        $reloaded = $this->ticketsTable->get($ticket->get('id'));
        $second = $this->ticketsTable->getBehavior('Workflow')
            ->transition($reloaded, 'touch', ['_idempotency_key' => 'KEY-1'], $options);

        $this->assertFalse($second->isSuccess());
        $this->assertTrue($second->isBlocked());
        $this->assertArrayHasKey('idempotency', $second->getBlockedBy());

        // A different key is allowed through.
        $third = $this->ticketsTable->getBehavior('Workflow')
            ->transition($reloaded, 'touch', ['_idempotency_key' => 'KEY-2'], $options);
        $this->assertTrue($third->isSuccess());
    }

    public function testPriorNonSuccessfulAttemptWithSameKeyDoesNotBlockReplay(): void
    {
        $ticket = $this->ticketsTable->newEntity(['state' => 'open']);
        $this->ticketsTable->saveOrFail($ticket);

        // A previously logged but BLOCKED attempt carrying the same key must not
        // be treated as a duplicate of a later legitimate application.
        $transitions = $this->fetchTable('Workflow.WorkflowTransitions');
        $transitions->saveOrFail($transitions->newEntity([
            'workflow_name' => 'ticket',
            'entity_table' => 'Tickets',
            'entity_id' => (string)$ticket->get('id'),
            'transition_name' => 'touch',
            'from_state' => 'open',
            'to_state' => 'open',
            'status' => 'blocked',
            'context' => ['_idempotency_key' => 'KEY-3'],
            'idempotency_key' => 'KEY-3',
            'created' => DateTime::now(),
        ]));

        $result = $this->ticketsTable->getBehavior('Workflow')
            ->transition($ticket, 'touch', ['_idempotency_key' => 'KEY-3'], ['log' => true, 'lock' => true]);

        $this->assertTrue($result->isSuccess());
    }

    public function testKeyWithLikeAndJsonMetacharactersMatchesExactly(): void
    {
        // A key full of LIKE wildcards and JSON-significant characters must match
        // only itself - exact column comparison, no LIKE/JSON fragility.
        $key = 'a%_"\\b';
        $options = ['log' => true, 'lock' => true];

        $ticket = $this->ticketsTable->newEntity(['state' => 'open']);
        $this->ticketsTable->saveOrFail($ticket);

        $first = $this->ticketsTable->getBehavior('Workflow')
            ->transition($ticket, 'touch', ['_idempotency_key' => $key], $options);
        $this->assertTrue($first->isSuccess());

        // Same exotic key -> duplicate.
        $reloaded = $this->ticketsTable->get($ticket->get('id'));
        $replay = $this->ticketsTable->getBehavior('Workflow')
            ->transition($reloaded, 'touch', ['_idempotency_key' => $key], $options);
        $this->assertTrue($replay->isBlocked());

        // A different key that the old LIKE pattern could have falsely matched is allowed.
        $other = $this->ticketsTable->getBehavior('Workflow')
            ->transition($reloaded, 'touch', ['_idempotency_key' => 'axyzb'], $options);
        $this->assertTrue($other->isSuccess());
    }

    private function createMockRegistry(): WorkflowRegistry
    {
        $definition = new Definition(
            name: 'ticket',
            table: 'Tickets',
            field: 'state',
            states: [
                new State('open', initial: true),
                new State('closed'),
            ],
            transitions: [
                // Self-loop so state never blocks a replay; idempotency must.
                new Transition('touch', ['open'], 'open'),
                new Transition('close', ['open'], 'closed'),
            ],
        );

        $loader = $this->createMock(LoaderInterface::class);
        $loader->method('supports')->willReturn(true);
        $loader->method('load')->willReturn($definition);

        return new WorkflowRegistry($loader, EventManager::instance());
    }
}
