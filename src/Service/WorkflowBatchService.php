<?php

declare(strict_types=1);

namespace Workflow\Service;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use InvalidArgumentException;
use Workflow\Model\Behavior\WorkflowBehavior;

/**
 * Service for applying workflow transitions to multiple entities.
 *
 * Provides batch operations for transitioning entities, with result
 * tracking for success/failure analysis.
 *
 * Example usage:
 * ```php
 * $batchService = new WorkflowBatchService();
 *
 * // Transition all pending orders older than 7 days
 * $query = $ordersTable->find()
 *     ->where(['state' => 'pending', 'created <' => $sevenDaysAgo]);
 * $result = $batchService->applyToQuery($ordersTable, $query, 'expire');
 *
 * // Or transition by state directly
 * $result = $batchService->applyToState($ordersTable, 'pending', 'remind');
 *
 * echo "Processed: {$result->getSuccessCount()}/{$result->getTotal()}";
 * ```
 */
class WorkflowBatchService
{
    /**
     * Apply a transition to all entities returned by a query.
     *
     * @param \Cake\ORM\Table&\Workflow\Model\WorkflowTableInterface $table Table with WorkflowBehavior attached
     * @param \Cake\ORM\Query\SelectQuery $query Query returning entities to transition
     * @param string $transition Transition name to apply
     * @param array<string, mixed> $context Context passed to each transition
     * @param bool $stopOnFailure If true, stops processing after first failure
     */
    public function applyToQuery(
        Table $table,
        SelectQuery $query,
        string $transition,
        array $context = [],
        bool $stopOnFailure = false,
    ): BatchResult {
        $behavior = $this->behavior($table);

        $result = new BatchResult();

        foreach ($query->all() as $entity) {
            /** @var \Cake\Datasource\EntityInterface $entity */
            // transition() persists each record (save + log, plus lock when enabled),
            // so a batch run actually advances them - not just their in-memory state.
            $transitionResult = $behavior->transition($entity, $transition, $context);
            $result->add($entity, $transitionResult);

            if ($stopOnFailure && !$transitionResult->isSuccess()) {
                break;
            }
        }

        return $result;
    }

    /**
     * Apply a transition to all entities currently in a given state.
     *
     * @param \Cake\ORM\Table&\Workflow\Model\WorkflowTableInterface $table Table with WorkflowBehavior attached
     * @param string $fromState State to find entities in
     * @param string $transition Transition name to apply
     * @param array<string, mixed> $context Context passed to each transition
     * @param int|null $limit Maximum number of entities to process (null = no limit)
     * @param bool $stopOnFailure If true, stops processing after first failure
     */
    public function applyToState(
        Table $table,
        string $fromState,
        string $transition,
        array $context = [],
        ?int $limit = null,
        bool $stopOnFailure = false,
    ): BatchResult {
        $definition = $this->behavior($table)->getWorkflowDefinition();
        $field = $definition->getField();

        $query = $table->find()->where([$table->aliasField($field) => $fromState]);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $this->applyToQuery($table, $query, $transition, $context, $stopOnFailure);
    }

    /**
     * Apply a transition to a list of entities.
     *
     * @param \Cake\ORM\Table&\Workflow\Model\WorkflowTableInterface $table Table with WorkflowBehavior attached
     * @param array<mixed> $entities Entities to transition
     * @param string $transition Transition name to apply
     * @param array<string, mixed> $context Context passed to each transition
     * @param bool $stopOnFailure If true, stops processing after first failure
     */
    public function applyToEntities(
        Table $table,
        array $entities,
        string $transition,
        array $context = [],
        bool $stopOnFailure = false,
    ): BatchResult {
        $behavior = $this->behavior($table);

        $result = new BatchResult();

        foreach ($entities as $entity) {
            if (!$entity instanceof EntityInterface) {
                continue;
            }

            $transitionResult = $behavior->transition($entity, $transition, $context);
            $result->add($entity, $transitionResult);

            if ($stopOnFailure && !$transitionResult->isSuccess()) {
                break;
            }
        }

        return $result;
    }

    /**
     * Apply a transition to entities matching a finder.
     *
     * @param \Cake\ORM\Table&\Workflow\Model\WorkflowTableInterface $table Table with WorkflowBehavior attached
     * @param string $finder Finder name (e.g., 'pending', 'expiring')
     * @param string $transition Transition name to apply
     * @param array<string, mixed> $finderOptions Options passed to the finder
     * @param array<string, mixed> $context Context passed to each transition
     * @param bool $stopOnFailure If true, stops processing after first failure
     */
    public function applyToFinder(
        Table $table,
        string $finder,
        string $transition,
        array $finderOptions = [],
        array $context = [],
        bool $stopOnFailure = false,
    ): BatchResult {
        $this->assertHasWorkflowBehavior($table);

        $query = $table->find($finder, ...$finderOptions);

        return $this->applyToQuery($table, $query, $transition, $context, $stopOnFailure);
    }

    /**
     * Assert that a table has the WorkflowBehavior attached.
     *
     * @param \Cake\ORM\Table $table
     *
     * @throws \InvalidArgumentException
     */
    private function assertHasWorkflowBehavior(Table $table): void
    {
        if (!$table->hasBehavior('Workflow')) {
            throw new InvalidArgumentException(
                sprintf('Table `%s` must have WorkflowBehavior attached', $table->getAlias()),
            );
        }
    }

    /**
     * Resolve the Workflow behavior, calling its methods directly rather than via the
     * table instance (table-level behavior method calls are deprecated in CakePHP 5.3).
     */
    private function behavior(Table $table): WorkflowBehavior
    {
        $this->assertHasWorkflowBehavior($table);
        /** @var \Workflow\Model\Behavior\WorkflowBehavior $behavior */
        $behavior = $table->getBehavior('Workflow');

        return $behavior;
    }
}
