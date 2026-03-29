<?php

declare(strict_types=1);

namespace Workflow\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class WorkflowTransitionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_transitions');
        $this->setDisplayField('transition_name');
        $this->setPrimaryKey('id');

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
            ->scalar('transition_name')
            ->maxLength('transition_name', 64)
            ->requirePresence('transition_name', 'create')
            ->notEmptyString('transition_name');

        $validator
            ->scalar('from_state')
            ->maxLength('from_state', 64)
            ->requirePresence('from_state', 'create')
            ->notEmptyString('from_state');

        $validator
            ->scalar('to_state')
            ->maxLength('to_state', 64)
            ->requirePresence('to_state', 'create')
            ->notEmptyString('to_state');

        return $validator;
    }

    /**
     * Find transitions for a specific entity.
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
                'entity_table' => $table,
                'entity_id' => $id,
            ])
            ->orderBy(['id' => 'DESC']);
    }

    /**
     * Find recent transitions across all entities.
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param int $limit
     */
    public function findRecent(SelectQuery $query, int $limit = 50): SelectQuery
    {
        return $query
            ->orderBy(['id' => 'DESC'])
            ->limit($limit);
    }
}
