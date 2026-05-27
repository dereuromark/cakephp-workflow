<?php
declare(strict_types=1);

return [
    [
        'table' => 'workflow_transitions',
        'columns' => [
            'id' => ['type' => 'integer', 'autoIncrement' => true],
            'workflow_name' => ['type' => 'string', 'length' => 64, 'null' => false],
            'model' => ['type' => 'string', 'length' => 128, 'null' => false],
            'foreign_key' => ['type' => 'integer', 'null' => false],
            'transition_name' => ['type' => 'string', 'length' => 64, 'null' => false],
            'from_state' => ['type' => 'string', 'length' => 64, 'null' => false],
            'to_state' => ['type' => 'string', 'length' => 64, 'null' => false],
            'status' => ['type' => 'string', 'length' => 16, 'null' => false, 'default' => 'success'],
            'user_id' => ['type' => 'string', 'length' => 36, 'null' => true],
            'reason' => ['type' => 'text', 'null' => true],
            'context' => ['type' => 'text', 'null' => true],
            'idempotency_key' => ['type' => 'string', 'length' => 128, 'null' => true],
            'workflow_version' => ['type' => 'string', 'length' => 16, 'null' => true],
            'created' => ['type' => 'datetime', 'null' => false],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
    [
        'table' => 'workflow_locks',
        'columns' => [
            'id' => ['type' => 'integer', 'autoIncrement' => true],
            'workflow_name' => ['type' => 'string', 'length' => 64, 'null' => false],
            'model' => ['type' => 'string', 'length' => 128, 'null' => false],
            'foreign_key' => ['type' => 'integer', 'null' => false],
            'locked_by' => ['type' => 'string', 'length' => 128, 'null' => true],
            'expires_at' => ['type' => 'datetime', 'null' => false],
            'created' => ['type' => 'datetime', 'null' => false],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'workflow_locks_unique' => [
                'type' => 'unique',
                'columns' => ['workflow_name', 'model', 'foreign_key'],
            ],
        ],
    ],
    [
        'table' => 'workflow_timeouts',
        'columns' => [
            'id' => ['type' => 'integer', 'autoIncrement' => true],
            'workflow_name' => ['type' => 'string', 'length' => 64, 'null' => false],
            'model' => ['type' => 'string', 'length' => 128, 'null' => false],
            'foreign_key' => ['type' => 'integer', 'null' => false],
            'current_state' => ['type' => 'string', 'length' => 64, 'null' => false],
            'transition_name' => ['type' => 'string', 'length' => 64, 'null' => false],
            'due_at' => ['type' => 'datetime', 'null' => false],
            'processed' => ['type' => 'boolean', 'default' => false, 'null' => false],
            'created' => ['type' => 'datetime', 'null' => false],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ],
];
