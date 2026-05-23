<?php

declare(strict_types=1);

namespace Workflow\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $workflow_name
 * @property string $model
 * @property int|string $foreign_key
 * @property string $current_state
 * @property string $transition_name
 * @property \Cake\I18n\DateTime $due_at
 * @property bool $processed
 * @property \Cake\I18n\DateTime $created
 */
class WorkflowTimeout extends Entity
{
    protected array $_accessible = [
        'workflow_name' => true,
        'model' => true,
        'foreign_key' => true,
        'current_state' => true,
        'transition_name' => true,
        'due_at' => true,
        'processed' => true,
        'created' => true,
    ];

    /**
     * Check if the timeout is due.
     */
    public function isDue(): bool
    {
        return $this->due_at->isPast() && !$this->processed;
    }
}
