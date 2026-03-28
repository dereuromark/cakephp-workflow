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
}
