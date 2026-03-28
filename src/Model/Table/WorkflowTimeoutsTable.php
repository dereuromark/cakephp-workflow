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
            ->scalar('entity_table')
            ->maxLength('entity_table', 128)
            ->requirePresence('entity_table', 'create')
            ->notEmptyString('entity_table');

        $validator
            ->scalar('entity_id')
            ->maxLength('entity_id', 36)
            ->requirePresence('entity_id', 'create')
            ->notEmptyString('entity_id');

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
     * @param array<string, mixed> $options
     */
    public function findDue(SelectQuery $query, array $options): SelectQuery
    {
        $limit = $options['limit'] ?? 100;

        return $query
            ->where([
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
     * @param array<string, mixed> $options
     */
    public function findForEntity(SelectQuery $query, array $options): SelectQuery
    {
        return $query
            ->where([
                'workflow_name' => $options['workflow'],
                'entity_table' => $options['table'],
                'entity_id' => $options['id'],
                'processed' => false,
            ]);
    }
}
