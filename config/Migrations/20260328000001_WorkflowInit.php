<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class WorkflowInit extends BaseMigration
{
    public function change(): void
    {
        // Workflow transitions log table
        $this->table('workflow_transitions')
            ->addColumn('workflow_name', 'string', [
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('entity_table', 'string', [
                'limit' => 128,
                'null' => false,
            ])
            ->addColumn('entity_id', 'biginteger', [
                'null' => false,
            ])
            ->addColumn('transition_name', 'string', [
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('from_state', 'string', [
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('to_state', 'string', [
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('user_id', 'string', [
                'limit' => 36,
                'null' => true,
            ])
            ->addColumn('reason', 'text', [
                'null' => true,
            ])
            ->addColumn('context', 'text', [
                'null' => true,
            ])
            ->addColumn('workflow_version', 'string', [
                'limit' => 16,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['workflow_name', 'entity_table', 'entity_id'])
            ->addIndex(['workflow_name', 'from_state'])
            ->addIndex(['created'])
            ->create();

        // Workflow locks table
        $this->table('workflow_locks')
            ->addColumn('workflow_name', 'string', [
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('entity_table', 'string', [
                'limit' => 128,
                'null' => false,
            ])
            ->addColumn('entity_id', 'biginteger', [
                'null' => false,
            ])
            ->addColumn('locked_by', 'string', [
                'limit' => 128,
                'null' => true,
            ])
            ->addColumn('expires_at', 'datetime', [
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addIndex(
                ['workflow_name', 'entity_table', 'entity_id'],
                ['unique' => true, 'name' => 'workflow_locks_unique'],
            )
            ->create();

        // Workflow timeouts table
        $this->table('workflow_timeouts')
            ->addColumn('workflow_name', 'string', [
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('entity_table', 'string', [
                'limit' => 128,
                'null' => false,
            ])
            ->addColumn('entity_id', 'biginteger', [
                'null' => false,
            ])
            ->addColumn('current_state', 'string', [
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('transition_name', 'string', [
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('due_at', 'datetime', [
                'null' => false,
            ])
            ->addColumn('processed', 'boolean', [
                'default' => false,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addIndex(['due_at', 'processed'])
            ->addIndex(['workflow_name', 'entity_table', 'entity_id'])
            ->create();
    }
}
