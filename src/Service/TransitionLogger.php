<?php
declare(strict_types=1);

namespace Workflow\Service;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Locator\LocatorAwareTrait;
use Workflow\Engine\TransitionResult;
use Workflow\Model\Entity\WorkflowTransition;

class TransitionLogger
{
    use LocatorAwareTrait;

    /**
     * Log a transition result.
     *
     * @param array<string, mixed> $context
     */
    public function log(
        string $workflowName,
        string $entityTable,
        EntityInterface $entity,
        TransitionResult $result,
        string $transitionName,
        array $context = [],
        ?string $workflowVersion = null,
    ): void {
        if (!$result->isSuccess()) {
            return;
        }

        $table = $this->fetchTable('Workflow.WorkflowTransitions');

        $transition = $table->newEntity([
            'workflow_name' => $workflowName,
            'entity_table' => $entityTable,
            'entity_id' => (string) $entity->get('id'),
            'transition_name' => $transitionName,
            'from_state' => $result->getFromState(),
            'to_state' => $result->getToState(),
            'user_id' => $context['user_id'] ?? null,
            'reason' => $context['reason'] ?? null,
            'context' => !empty($context) ? json_encode($context) : null,
            'workflow_version' => $workflowVersion,
        ]);

        $table->saveOrFail($transition);
    }

    /**
     * Get transition history for an entity.
     *
     * @return array<WorkflowTransition>
     */
    public function getHistory(
        string $workflowName,
        string $entityTable,
        string $entityId,
    ): array {
        $table = $this->fetchTable('Workflow.WorkflowTransitions');

        return $table->find('forEntity', [
            'workflow' => $workflowName,
            'table' => $entityTable,
            'id' => $entityId,
        ])->toArray();
    }
}
