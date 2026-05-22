<?php

declare(strict_types=1);

namespace Workflow\Model\Table;

use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Workflow\Model\Entity\WorkflowLock;

class WorkflowLocksTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_locks');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');
        $this->setEntityClass(WorkflowLock::class);

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
            ->dateTime('expires_at')
            ->requirePresence('expires_at', 'create')
            ->notEmptyDateTime('expires_at');

        return $validator;
    }

    /**
     * Find active lock for entity.
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param string $workflow
     * @param string $table
     * @param string $id
     */
    public function findActiveLock(SelectQuery $query, string $workflow, string $table, string $id): SelectQuery
    {
        return $query
            ->where([
                'workflow_name' => $workflow,
                'entity_table' => $table,
                'entity_id' => $id,
                'expires_at >=' => $this->getCurrentTime(),
            ]);
    }

    /**
     * Delete expired locks.
     */
    public function deleteExpired(): int
    {
        return $this->deleteAll([
            'expires_at <' => $this->getCurrentTime(),
        ]);
    }

    /**
     * Get current time for lock comparisons, in the app's configured timezone — the
     * same basis lock rows are written with, so writes and reads always agree.
     */
    protected function getCurrentTime(): DateTime
    {
        return DateTime::now();
    }
}
