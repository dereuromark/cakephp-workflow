<?php

declare(strict_types=1);

namespace Workflow\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Workflow\Service\TransitionLogger;

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

        // Enable JSON decoding for context field
        $this->getSchema()->setColumnType('context', 'json');
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

        $validator
            ->scalar('status')
            ->maxLength('status', 16)
            ->requirePresence('status', 'create')
            ->notEmptyString('status')
            ->inList('status', [
                TransitionLogger::STATUS_SUCCESS,
                TransitionLogger::STATUS_BLOCKED,
                TransitionLogger::STATUS_LOCKED,
                TransitionLogger::STATUS_ERROR,
            ]);

        return $validator;
    }

    /**
     * Find transitions for a specific entity.
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param string $workflow
     * @param string $table
     * @param string $id
     * @param bool $successOnly If true, only return successful transitions
     */
    public function findForEntity(
        SelectQuery $query,
        string $workflow,
        string $table,
        string $id,
        bool $successOnly = false,
    ): SelectQuery {
        $query = $query
            ->where([
                'workflow_name' => $workflow,
                'entity_table' => $table,
                'entity_id' => $id,
            ])
            ->orderBy(['id' => 'DESC']);

        if ($successOnly) {
            $query = $query->where(['status' => TransitionLogger::STATUS_SUCCESS]);
        }

        return $query;
    }

    /**
     * Find recent transitions across all entities.
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param int $limit
     * @param string|null $status Filter by status (success, blocked, locked, error)
     */
    public function findRecent(SelectQuery $query, int $limit = 50, ?string $status = null): SelectQuery
    {
        $query = $query
            ->orderBy(['id' => 'DESC'])
            ->limit($limit);

        if ($status !== null) {
            $query = $query->where(['status' => $status]);
        }

        return $query;
    }

    /**
     * Find transitions by status.
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param string $status One of: success, blocked, locked, error
     */
    public function findByStatus(SelectQuery $query, string $status): SelectQuery
    {
        return $query
            ->where(['status' => $status])
            ->orderBy(['id' => 'DESC']);
    }

    /**
     * Find failed transitions (blocked, locked, or error).
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     */
    public function findFailed(SelectQuery $query): SelectQuery
    {
        return $query
            ->where([

                'status IN' => [
                    TransitionLogger::STATUS_BLOCKED,
                    TransitionLogger::STATUS_LOCKED,
                    TransitionLogger::STATUS_ERROR,
                ],
            ])
            ->orderBy(['id' => 'DESC']);
    }
}
