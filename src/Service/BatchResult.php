<?php

declare(strict_types=1);

namespace Workflow\Service;

use Cake\Datasource\EntityInterface;
use Workflow\Engine\TransitionResult;

/**
 * Result of a batch transition operation.
 *
 * Contains individual results for each entity processed,
 * with convenience methods for analyzing overall success/failure.
 */
final class BatchResult
{
    /**
     * @var array<array{entity: \Cake\Datasource\EntityInterface, result: \Workflow\Engine\TransitionResult}>
     */
    private array $results;

    /**
     * @param array<array{entity: \Cake\Datasource\EntityInterface, result: \Workflow\Engine\TransitionResult}> $results
     */
    public function __construct(array $results = [])
    {
        $this->results = $results;
    }

    /**
     * Add a result to the batch.
     */
    public function add(EntityInterface $entity, TransitionResult $result): void
    {
        $this->results[] = ['entity' => $entity, 'result' => $result];
    }

    /**
     * Get all results.
     *
     * @return array<array{entity: \Cake\Datasource\EntityInterface, result: \Workflow\Engine\TransitionResult}>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get total number of entities processed.
     */
    public function getTotal(): int
    {
        return count($this->results);
    }

    /**
     * Get number of successful transitions.
     */
    public function getSuccessCount(): int
    {
        return count(array_filter(
            $this->results,
            fn (array $r) => $r['result']->isSuccess(),
        ));
    }

    /**
     * Get number of failed transitions (blocked, locked, or error).
     */
    public function getFailureCount(): int
    {
        return $this->getTotal() - $this->getSuccessCount();
    }

    /**
     * Check if all transitions succeeded.
     */
    public function isFullSuccess(): bool
    {
        return $this->getTotal() > 0 && $this->getFailureCount() === 0;
    }

    /**
     * Check if any transitions succeeded.
     */
    public function hasSuccesses(): bool
    {
        return $this->getSuccessCount() > 0;
    }

    /**
     * Check if any transitions failed.
     */
    public function hasFailures(): bool
    {
        return $this->getFailureCount() > 0;
    }

    /**
     * Get only successful results.
     *
     * @return array<array{entity: \Cake\Datasource\EntityInterface, result: \Workflow\Engine\TransitionResult}>
     */
    public function getSuccesses(): array
    {
        return array_values(array_filter(
            $this->results,
            fn (array $r) => $r['result']->isSuccess(),
        ));
    }

    /**
     * Get only failed results.
     *
     * @return array<array{entity: \Cake\Datasource\EntityInterface, result: \Workflow\Engine\TransitionResult}>
     */
    public function getFailures(): array
    {
        return array_values(array_filter(
            $this->results,
            fn (array $r) => !$r['result']->isSuccess(),
        ));
    }

    /**
     * Get entities that transitioned successfully.
     *
     * @return array<\Cake\Datasource\EntityInterface>
     */
    public function getSuccessfulEntities(): array
    {
        return array_map(
            fn (array $r) => $r['entity'],
            $this->getSuccesses(),
        );
    }

    /**
     * Get entities that failed to transition.
     *
     * @return array<\Cake\Datasource\EntityInterface>
     */
    public function getFailedEntities(): array
    {
        return array_map(
            fn (array $r) => $r['entity'],
            $this->getFailures(),
        );
    }
}
