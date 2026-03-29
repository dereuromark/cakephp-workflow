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
     * Status constants matching TransitionResult statuses.
     */
    public const STATUS_SUCCESS = 'success';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_ERROR = 'error';

    /**
     * Log a transition result.
     *
     * Logs all transition attempts (success, blocked, locked, error) for complete audit trail.
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
        $table = $this->fetchTable('Workflow.WorkflowTransitions');

        // Include runtime metadata in context
        $contextWithRuntime = $context;
        $runtime = $this->buildRuntimeMetadata($result);
        if ($runtime) {
            $contextWithRuntime['_runtime'] = $runtime;
        }

        // Add blocked reasons or error to context
        if ($result->isBlocked()) {
            $contextWithRuntime['_blocked_by'] = $result->getBlockedBy();
        } elseif ($result->isError()) {
            $error = $result->getError();
            $contextWithRuntime['_error'] = [
                'message' => $error?->getMessage(),
                'class' => $error !== null ? get_class($error) : null,
                'file' => $error?->getFile(),
                'line' => $error?->getLine(),
            ];
        } elseif ($result->isLocked()) {
            $contextWithRuntime['_locked'] = true;
        }

        $transition = $table->newEntity([
            'workflow_name' => $workflowName,
            'entity_table' => $entityTable,
            'entity_id' => (string)$entity->get('id'),
            'transition_name' => $transitionName,
            'from_state' => $result->getFromState(),
            'to_state' => $result->getToState() ?? $result->getFromState(), // Stay in same state if blocked
            'status' => $this->getStatusFromResult($result),
            'user_id' => $context['user_id'] ?? null,
            'reason' => $context['reason'] ?? null,
            'context' => $contextWithRuntime ? $this->encodeContext($contextWithRuntime) : null,
            'workflow_version' => $workflowVersion,
        ]);

        $table->saveOrFail($transition);
    }

    /**
     * Get status string from TransitionResult.
     */
    private function getStatusFromResult(TransitionResult $result): string
    {
        if ($result->isSuccess()) {
            return self::STATUS_SUCCESS;
        }
        if ($result->isBlocked()) {
            return self::STATUS_BLOCKED;
        }
        if ($result->isLocked()) {
            return self::STATUS_LOCKED;
        }
        if ($result->isError()) {
            return self::STATUS_ERROR;
        }

        // Fallback (should not happen)
        return self::STATUS_ERROR;
    }

    /**
     * Build runtime metadata array from transition result.
     *
     * @param \Workflow\Engine\TransitionResult $result
     *
     * @return array<string, mixed>|null
     */
    private function buildRuntimeMetadata(TransitionResult $result): ?array
    {
        $runtime = [];

        $guardsEvaluated = $result->getGuardsEvaluated();
        if ($guardsEvaluated) {
            $runtime['guards_evaluated'] = $guardsEvaluated;
        }

        $commandsExecuted = $result->getCommandsExecuted();
        if ($commandsExecuted) {
            $runtime['commands_executed'] = $commandsExecuted;
        }

        if ($result->usedLock()) {
            $runtime['used_lock'] = true;
        }

        return $runtime ?: null;
    }

    /**
     * Get transition history for an entity.
     *
     * @param string $workflowName
     * @param string $entityTable
     * @param string $entityId
     * @param bool $successOnly If true, only return successful transitions
     *
     * @return array<\Workflow\Model\Entity\WorkflowTransition>
     */
    public function getHistory(
        string $workflowName,
        string $entityTable,
        string $entityId,
        bool $successOnly = false,
    ): array {
        /** @var \Workflow\Model\Table\WorkflowTransitionsTable $table */
        $table = $this->fetchTable('Workflow.WorkflowTransitions');

        /** @var array<\Workflow\Model\Entity\WorkflowTransition> $result */
        $result = $table->find('forEntity', workflow: $workflowName, table: $entityTable, id: $entityId, successOnly: $successOnly)->toArray();

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
