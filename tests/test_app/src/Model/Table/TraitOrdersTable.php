<?php

declare(strict_types=1);

namespace TestApp\Model\Table;

use Cake\ORM\Table;
use TestApp\Model\Entity\TraitOrder;
use Workflow\Model\Table\WorkflowTableTrait;

class TraitOrdersTable extends Table
{
    use WorkflowTableTrait;

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('orders');
        $this->setEntityClass(TraitOrder::class);
        $this->addBehavior('Workflow.Workflow', ['workflow' => 'order', 'useLocking' => false]);
    }
}
