<?php

declare(strict_types=1);

namespace Workflow\Model\Table;

use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Workflow\Model\Entity\WorkflowTimeout;

class WorkflowTimeoutsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_timeouts');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');
        $this->setEntityClass(WorkflowTimeout::class);

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                ],
            ],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('workflow_name')
            ->maxLength('workflow_name', 64)
            ->requirePresence('workflow_name', 'create')
            ->notEmptyString('workflow_name');

        $validator
            ->scalar('model')
            ->maxLength('model', 128)
            ->requirePresence('model', 'create')
            ->notEmptyString('model');

        $validator
            ->scalar('foreign_key')
            ->maxLength('foreign_key', 255)
            ->requirePresence('foreign_key', 'create')
            ->notEmptyString('foreign_key');

        $validator
            ->scalar('current_state')
            ->maxLength('current_state', 64)
            ->requirePresence('current_state', 'create')
            ->notEmptyString('current_state');

        $validator
            ->scalar('transition_name')
            ->maxLength('transition_name', 64)
            ->requirePresence('transition_name', 'create')
            ->notEmptyString('transition_name');

        $validator
            ->dateTime('due_at')
            ->requirePresence('due_at', 'create')
            ->notEmptyDateTime('due_at');

        return $validator;
    }

    /**
     * Find pending timeouts that are due.
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param int $limit
     */
    public function findDue(SelectQuery $query, int $limit = 100): SelectQuery
    {
        return $query
            ->where([
                // Compare in the app timezone, the same basis due_at is written with
                // (TimeoutScheduler), so writes and reads always agree.
                'due_at <=' => DateTime::now(),
                'processed' => false,
            ])
            ->orderBy(['due_at' => 'ASC'])
            ->limit($limit);
    }

    /**
     * Find timeouts for a specific entity.
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param string $workflow
     * @param string $table
     * @param string $id
     */
    public function findForEntity(SelectQuery $query, string $workflow, string $table, string $id): SelectQuery
    {
        return $query
            ->where([
                'workflow_name' => $workflow,
                'model' => $table,
                'foreign_key' => $id,
                'processed' => false,
            ]);
    }

    public function markPendingProcessed(string $workflow, string $table, string $id): int
    {
        return $this->updateAll(
            ['processed' => true],
            [
                'workflow_name' => $workflow,
                'model' => $table,
                'foreign_key' => $id,
                'processed' => false,
            ],
        );
    }
}
