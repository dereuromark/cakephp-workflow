<?php

declare(strict_types=1);

namespace Workflow\Service;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Locator\LocatorAwareTrait;
use Workflow\Engine\TransitionResult;

class TransitionLogger
{
    use LocatorAwareTrait;

    /**
     * Log a transition result.
     *
     * @param string $workflowName
     * @param string $entityTable
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \Workflow\Engine\TransitionResult $result
     * @param string $transitionName
     * @param array<string, mixed> $context
     * @param string|null $workflowVersion
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
            'entity_id' => (string)$entity->get('id'),
            'transition_name' => $transitionName,
            'from_state' => $result->getFromState(),
            'to_state' => $result->getToState(),
            'user_id' => $context['user_id'] ?? null,
            'reason' => $context['reason'] ?? null,
            'context' => $context ? $this->encodeContext($context) : null,
            'workflow_version' => $workflowVersion,
        ]);

        $table->saveOrFail($transition);
    }

    /**
     * Get transition history for an entity.
     *
     * @return array<\Workflow\Model\Entity\WorkflowTransition>
     */
    public function getHistory(
        string $workflowName,
        string $entityTable,
        string $entityId,
    ): array {
        /** @var \Workflow\Model\Table\WorkflowTransitionsTable $table */
        $table = $this->fetchTable('Workflow.WorkflowTransitions');

        /** @var array<\Workflow\Model\Entity\WorkflowTransition> $result */
        $result = $table->find('forEntity', [
            'workflow' => $workflowName,
            'table' => $entityTable,
            'id' => $entityId,
        ])->toArray();

        return $result;
    }

    /**
     * Encode context array to JSON string.
     *
     * @param array<string, mixed> $context
     */
    private function encodeContext(array $context): ?string
    {
        $json = json_encode($context);
        if ($json === false) {
            // Fallback: try encoding with error handling
            $json = json_encode($context, JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        return $json !== false ? $json : null;
    }
}
