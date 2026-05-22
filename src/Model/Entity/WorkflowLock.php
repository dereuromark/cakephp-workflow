<?php

declare(strict_types=1);

namespace Workflow\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $workflow_name
 * @property string $entity_table
 * @property int|string $entity_id
 * @property string|null $locked_by
 * @property \Cake\I18n\DateTime $expires_at
 * @property \Cake\I18n\DateTime $created
 */
class WorkflowLock extends Entity
{
    protected array $_accessible = [
        'workflow_name' => true,
        'entity_table' => true,
        'entity_id' => true,
        'locked_by' => true,
        'expires_at' => true,
        'created' => true,
    ];

    /**
     * Check if the lock has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
