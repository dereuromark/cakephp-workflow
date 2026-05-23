<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Migrations\BaseMigration;

class WorkflowInit extends BaseMigration
{
    public function change(): void
    {
        // foreign_key is polymorphic (paired with model) — it stores the primary
        // key of whatever source table a row belongs to. Its column type follows the
        // shared Polymorphic.type key (integer default; biginteger / uuid / binaryuuid),
        // the same convention used across the plugin family.
        $foreignKeyType = (string)Configure::read('Polymorphic.type', 'integer');
        $foreignKeyOptions = ['null' => false];
        if (in_array($foreignKeyType, ['integer', 'biginteger'], true)) {
            // Match the application's primary-key signedness so foreign_key lines up with
            // the ids it references. Mirrors CakePHP Migrations' own default for this
            // flag (signed when unset). Unsigned only takes effect on MySQL.
            $foreignKeyOptions['signed'] = !(bool)Configure::read('Migrations.unsigned_primary_keys', false);
        }

        // Workflow transitions log table
        $this->table('workflow_transitions')
            ->addColumn('workflow_name', 'string', [
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('model', 'string', [
                'limit' => 128,
                'null' => false,
            ])
            ->addColumn('foreign_key', $foreignKeyType, $foreignKeyOptions)
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
            ->addIndex(['workflow_name', 'model', 'foreign_key'])
            ->addIndex(['workflow_name', 'from_state'])
            ->addIndex(['created'])
            ->create();

        // Workflow locks table
        $this->table('workflow_locks')
            ->addColumn('workflow_name', 'string', [
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('model', 'string', [
                'limit' => 128,
                'null' => false,
            ])
            ->addColumn('foreign_key', $foreignKeyType, $foreignKeyOptions)
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
                ['workflow_name', 'model', 'foreign_key'],
                ['unique' => true, 'name' => 'workflow_locks_unique'],
            )
            ->create();

        // Workflow timeouts table
        $this->table('workflow_timeouts')
            ->addColumn('workflow_name', 'string', [
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('model', 'string', [
                'limit' => 128,
                'null' => false,
            ])
            ->addColumn('foreign_key', $foreignKeyType, $foreignKeyOptions)
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
            ->addIndex(['workflow_name', 'model', 'foreign_key'])
            ->create();
    }
}
