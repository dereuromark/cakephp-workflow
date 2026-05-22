<?php
declare(strict_types=1);

return [
    [
        'table' => 'workflow_transitions',
        'columns' => [
            'id' => ['type' => 'integer', 'autoIncrement' => true],
            'workflow_name' => ['type' => 'string', 'length' => 64, 'null' => false],
            'entity_table' => ['type' => 'string', 'length' => 128, 'null' => false],
            'entity_id' => ['type' => 'string', 'length' => 36, 'null' => false],
            'transition_name' => ['type' => 'string', 'length' => 64, 'null' => false],
            'from_state' => ['type' => 'string', 'length' => 64, 'null' => false],
            'to_state' => ['type' => 'string', 'length' => 64, 'null' => false],
            'status' => ['type' => 'string', 'length' => 16, 'null' => false, 'default' => 'success'],
            'user_id' => ['type' => 'string', 'length' => 36, 'null' => true],
            'reason' => ['type' => 'text', 'null' => true],
            'context' => ['type' => 'text', 'null' => true],
            'idempotency_key' => ['type' => 'string', 'length' => 255, 'null' => true],
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
            'entity_table' => ['type' => 'string', 'length' => 128, 'null' => false],
            'entity_id' => ['type' => 'string', 'length' => 36, 'null' => false],
            'locked_by' => ['type' => 'string', 'length' => 128, 'null' => true],
            'expires_at' => ['type' => 'datetime', 'null' => false],
            'created' => ['type' => 'datetime', 'null' => false],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'workflow_locks_unique' => [
                'type' => 'unique',
                'columns' => ['workflow_name', 'entity_table', 'entity_id'],
            ],
        ],
    ],
    [
        'table' => 'workflow_timeouts',
        'columns' => [
            'id' => ['type' => 'integer', 'autoIncrement' => true],
            'workflow_name' => ['type' => 'string', 'length' => 64, 'null' => false],
            'entity_table' => ['type' => 'string', 'length' => 128, 'null' => false],
            'entity_id' => ['type' => 'string', 'length' => 36, 'null' => false],
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
