<?php
declare(strict_types=1);

namespace Workflow\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $workflow_name
 * @property string $entity_table
 * @property string $entity_id
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
        'entity_table' => true,
        'entity_id' => true,
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
