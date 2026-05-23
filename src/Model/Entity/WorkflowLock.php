<?php

declare(strict_types=1);

namespace Workflow\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $workflow_name
 * @property string $model
 * @property int|string $foreign_key
 * @property string|null $locked_by
 * @property \Cake\I18n\DateTime $expires_at
 * @property \Cake\I18n\DateTime $created
 */
class WorkflowLock extends Entity
{
    protected array $_accessible = [
        'workflow_name' => true,
        'model' => true,
        'foreign_key' => true,
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
