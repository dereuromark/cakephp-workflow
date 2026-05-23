<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Rename the polymorphic reference columns to the CakePHP ecosystem convention
 * (model / foreign_key), matching cakephp-comments / cakephp-favorites /
 * cakephp-file-storage.
 *
 * Data-preserving: renameColumn keeps the stored values and the columns'
 * membership in their indexes.
 *
 * Guarded so the chain is valid on a clean database too: a fresh install runs
 * WorkflowInit (which already creates model/foreign_key) and this migration then
 * finds nothing to rename and is a no-op. Existing 0.1.x installs still have the
 * old columns and get renamed here.
 */
class RenamePolymorphicColumns extends BaseMigration
{
    /**
     * @var array<string> The plugin tables carrying the polymorphic columns.
     */
    protected array $polymorphicTables = [
        'workflow_transitions',
        'workflow_locks',
        'workflow_timeouts',
    ];

    public function up(): void
    {
        $this->renameColumns(['entity_table' => 'model', 'entity_id' => 'foreign_key']);
    }

    public function down(): void
    {
        $this->renameColumns(['model' => 'entity_table', 'foreign_key' => 'entity_id']);
    }

    /**
     * @param array<string, string> $renames Map of existing column name => new name.
     */
    protected function renameColumns(array $renames): void
    {
        foreach ($this->polymorphicTables as $tableName) {
            $table = $this->table($tableName);
            $changed = false;
            foreach ($renames as $from => $to) {
                // Only rename when the source exists and the target does not, so the
                // migration stays a safe no-op on fresh installs (new names already
                // present) and on partially-migrated/hand-altered databases.
                if ($table->hasColumn($from) && !$table->hasColumn($to)) {
                    $table->renameColumn($from, $to);
                    $changed = true;
                }
            }
            if ($changed) {
                $table->update();
            }
        }
    }
}
