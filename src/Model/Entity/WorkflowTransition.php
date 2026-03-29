<?php

declare(strict_types=1);

namespace Workflow\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $workflow_name
 * @property string $entity_table
 * @property string $entity_id
 * @property string $transition_name
 * @property string $from_state
 * @property string $to_state
 * @property string|null $user_id
 * @property string|null $reason
 * @property array<string, mixed>|null $context
 * @property string|null $workflow_version
 * @property \Cake\I18n\DateTime $created
 */
class WorkflowTransition extends Entity
{
    protected array $_accessible = [
        'workflow_name' => true,
        'entity_table' => true,
        'entity_id' => true,
        'transition_name' => true,
        'from_state' => true,
        'to_state' => true,
        'user_id' => true,
        'reason' => true,
        'context' => true,
        'workflow_version' => true,
        'created' => true,
    ];

    /**
     * Get runtime metadata from context.
     *
     * @return array{guards_evaluated?: array<string>, commands_executed?: array<string>, used_lock?: bool}|null
     */
    public function getRuntime(): ?array
    {
        return $this->context['_runtime'] ?? null;
    }

    /**
     * Get guards that were evaluated during this transition.
     *
     * @return array<string>
     */
    public function getGuardsEvaluated(): array
    {
        return $this->context['_runtime']['guards_evaluated'] ?? [];
    }

    /**
     * Get commands that were executed during this transition.
     *
     * @return array<string>
     */
    public function getCommandsExecuted(): array
    {
        return $this->context['_runtime']['commands_executed'] ?? [];
    }

    /**
     * Check if a lock was used during this transition.
     */
    public function usedLock(): bool
    {
        return $this->context['_runtime']['used_lock'] ?? false;
    }
}
