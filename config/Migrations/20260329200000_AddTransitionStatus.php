<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Adds status column to workflow_transitions table to track success/blocked/error outcomes.
 *
 * This enables logging of all transition attempts, not just successful ones,
 * providing a complete audit trail for compliance and debugging.
 */
class AddTransitionStatus extends BaseMigration
{
    public function change(): void
    {
        $this->table('workflow_transitions')
            ->addColumn('status', 'string', [
                'limit' => 16,
                'null' => false,
                'default' => 'success',
                'after' => 'to_state',
            ])
            ->addIndex(['status'])
            ->update();
    }
}
