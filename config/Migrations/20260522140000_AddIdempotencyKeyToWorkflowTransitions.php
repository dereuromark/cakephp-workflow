<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Stores the idempotency key in its own indexed column instead of matching it as a
 * substring inside the JSON `context`. Exact-column matching is portable and not
 * vulnerable to LIKE wildcards or JSON escaping in the key.
 */
class AddIdempotencyKeyToWorkflowTransitions extends BaseMigration
{
    public function change(): void
    {
        $this->table('workflow_transitions')
            ->addColumn('idempotency_key', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null,
                'after' => 'context',
            ])
            ->addIndex(['idempotency_key'])
            ->update();
    }
}
