<?php

declare(strict_types=1);

namespace TestApp\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Workflow\Model\Behavior\WorkflowBehavior;

class BatchOrdersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('orders');
        $this->addBehavior('Workflow', [
            'className' => WorkflowBehavior::class,
            'workflow' => 'order',
            'useLocking' => false,
            'validateOnSave' => false,
        ]);
    }

    /**
     * Custom finder taking a required named argument, to exercise applyToFinder()
     * option passing.
     */
    public function findWithState(SelectQuery $query, string $state): SelectQuery
    {
        return $query->where([$this->aliasField('state') => $state]);
    }
}
