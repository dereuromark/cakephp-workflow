<?php

declare(strict_types=1);

namespace TestApp\Model\Entity;

use Cake\ORM\Entity;
use Workflow\Model\Entity\WorkflowTrait;

class TraitOrder extends Entity
{
    use WorkflowTrait;

    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];
}
