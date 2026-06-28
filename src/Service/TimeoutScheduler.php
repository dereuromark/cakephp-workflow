<?php

declare(strict_types=1);

namespace Workflow\Service;

use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use DateInterval;
use Throwable;
use Workflow\Engine\Definition\State;
use Workflow\Exception\WorkflowException;

class TimeoutScheduler
{
    use LocatorAwareTrait;

    public function syncStateTimeouts(
        string $workflowName,
        string $model,
        EntityInterface $entity,
        State $state,
    ): void {
        $foreignKey = (string)$entity->get('id');
        if ($foreignKey === '') {
            throw new WorkflowException('Cannot schedule workflow timeouts for an entity without an id');
        }

        /** @var \Workflow\Model\Table\WorkflowTimeoutsTable $table */
        $table = $this->fetchTable('Workflow.WorkflowTimeouts');
        $table->markPendingProcessed($workflowName, $model, $foreignKey);

        foreach ($state->getTimeouts() as $timeout) {
            $record = $table->newEntity([
                'workflow_name' => $workflowName,
                'model' => $model,
                'foreign_key' => $foreignKey,
                'current_state' => $state->getName(),
                'transition_name' => $timeout->getTransition(),
                'due_at' => $this->calculateDueAt($timeout->getAfter()),
                'processed' => false,
            ]);

            $table->saveOrFail($record);
        }
    }

    protected function calculateDueAt(string $after): DateTime
    {
        $base = DateTime::now();
        $interval = $this->parseInterval($after);

        return $base->add($interval);
    }

    protected function parseInterval(string $after): DateInterval
    {
        try {
            if (str_starts_with($after, 'P')) {
                return new DateInterval($after);
            }
            $interval = @DateInterval::createFromDateString($after);
        } catch (Throwable $exception) {
            throw new WorkflowException(sprintf('Invalid timeout duration "%s": %s', $after, $exception->getMessage()), (int)$exception->getCode(), previous: $exception);
        }

        return $interval;
    }

    public function isEnabled(): bool
    {
        return (bool)Configure::read('Workflow.timeouts', true);
    }
}
