<?php

declare(strict_types=1);

namespace Workflow\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use Exception;
use RuntimeException;
use Throwable;
use Workflow\Engine\Definition\Definition;
use Workflow\Service\TimeoutScheduler;
use Workflow\Service\TransitionLogger;
use Workflow\Service\WorkflowRegistry;
use Workflow\Service\WorkflowRegistryLocator;

/**
 * Reconcile records with the current definition after a workflow changes.
 *
 * - Stale records (valid state, outdated version) are re-stamped to the current hash.
 * - Orphaned records (state no longer defined) are moved to a mapped target state,
 *   re-stamped, and logged as a transition.
 *
 * Refuses to run when any orphaned state has no mapping, so no record is left behind
 * or silently lost.
 */
class WorkflowMigrateCommand extends Command
{
    use LocatorAwareTrait;
    use VersionFieldOptionTrait;

    public static function defaultName(): string
    {
        return 'workflow migrate';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Migrate records forward to the current workflow definition.')
            ->addArgument('name', [
                'help' => 'Workflow name',
                'required' => true,
            ])
            ->addOption('version-field', [
                'help' => 'Entity column holding the version stamp '
                    . '(defaults to the behavior\'s configured field, else workflow_version)',
            ])
            ->addOption('map', [
                'help' => 'Comma-separated old:new state mappings for orphaned records, '
                    . 'e.g. --map old_state:new_state,legacy:pending',
                'default' => '',
            ])
            ->addOption('dry-run', [
                'boolean' => true,
                'help' => 'Report planned changes without writing',
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $registry = $this->getRegistry();
        $name = (string)$args->getArgument('name');

        if (!$registry->hasWorkflow($name)) {
            $io->error("Workflow '{$name}' not found.");

            return self::CODE_ERROR;
        }

        $definition = $registry->getWorkflow($name);
        $hash = $definition->getVersionHash();

        try {
            $table = $this->fetchTable($definition->getTable());
        } catch (Exception $e) {
            $io->error("Could not load table '{$definition->getTable()}': " . $e->getMessage());

            return self::CODE_ERROR;
        }

        $versionField = $this->resolveVersionField($args, $table);

        if (!$table->getSchema()->hasColumn($versionField)) {
            $io->error(
                "Column '{$versionField}' does not exist on table '{$definition->getTable()}'. "
                . 'Add a nullable string column or pass --version-field.',
            );

            return self::CODE_ERROR;
        }

        $field = $definition->getField();
        $validStates = array_map(fn ($s) => $s->getName(), $definition->getStates());
        $map = $this->parseMap((string)$args->getOption('map'));

        // Validate mapping targets up front.
        foreach ($map as $old => $new) {
            if (!in_array($new, $validStates, true)) {
                $io->error("Mapping target '{$new}' is not a defined state in workflow '{$name}'.");

                return self::CODE_ERROR;
            }
        }

        $orphanStates = $this->findOrphanStates($table, $field, $validStates);

        $unmapped = array_values(array_filter($orphanStates, fn ($state) => !isset($map[$state])));
        if ($unmapped) {
            $io->error('Orphaned states without a mapping: ' . implode(', ', $unmapped));
            $io->out('Provide a mapping for each, e.g. --map ' . $unmapped[0] . ':' . ($validStates[0] ?? 'target'));

            return self::CODE_ERROR;
        }

        $staleConditions = [
            $field . ' IN' => $validStates,
            $versionField . ' IS NOT' => null,
            $versionField . ' !=' => $hash,
        ];
        $staleCount = $table->find()->where($staleConditions)->count();

        if ($args->getOption('dry-run')) {
            $io->out(sprintf('[dry-run] Would re-stamp %d stale record(s).', $staleCount));
            foreach ($orphanStates as $state) {
                $count = $table->find()->where([$field => $state])->count();
                $io->out(sprintf("[dry-run] Would migrate %d record(s) from '%s' to '%s'.", $count, $state, $map[$state]));
            }

            return self::CODE_SUCCESS;
        }

        // Apply re-stamping and migrations atomically: if any audit-log write fails,
        // the whole batch rolls back so no record is changed without its log entry.
        $syncTimeouts = $this->timeoutsEnabled($table);
        $migrated = [];
        try {
            $table->getConnection()->transactional(
                function () use ($table, $definition, $field, $versionField, $hash, $staleConditions, $staleCount, $orphanStates, $map, $syncTimeouts, &$migrated): void {
                    if ($staleCount > 0) {
                        $table->updateAll([$versionField => $hash], $staleConditions);
                    }

                    foreach ($orphanStates as $state) {
                        $migrated[$state] = $this->migrateState(
                            $table,
                            $definition,
                            $field,
                            $versionField,
                            $hash,
                            $state,
                            $map[$state],
                            $syncTimeouts,
                        );
                    }
                },
            );
        } catch (Exception $e) {
            $io->error('Migration aborted (no changes applied): ' . $e->getMessage());

            return self::CODE_ERROR;
        }

        if ($staleCount > 0) {
            $io->success(sprintf('Re-stamped %d stale record(s).', $staleCount));
        }
        foreach ($migrated as $state => $count) {
            $io->success(sprintf("Migrated %d record(s) from '%s' to '%s'.", $count, $state, $map[$state]));
        }

        return self::CODE_SUCCESS;
    }

    /**
     * Move every record in $oldState to $newState, re-stamp it, log a transition, and
     * (when enabled) resync the target state's timeouts.
     *
     * @return int Number of records migrated
     */
    private function migrateState(
        Table $table,
        Definition $definition,
        string $field,
        string $versionField,
        string $hash,
        string $oldState,
        string $newState,
        bool $syncTimeouts,
    ): int {
        $primaryKey = $table->getPrimaryKey();
        if (is_array($primaryKey)) {
            $primaryKey = $primaryKey[0];
        }

        /** @var array<\Cake\Datasource\EntityInterface> $entities */
        $entities = $table->find()->where([$field => $oldState])->toArray();
        if (!$entities) {
            return 0;
        }

        $table->updateAll([$field => $newState, $versionField => $hash], [$field => $oldState]);

        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
        $newStateObj = $definition->getState($newState);
        $scheduler = $syncTimeouts ? new TimeoutScheduler() : null;

        foreach ($entities as $entity) {
            $id = (string)$entity->get($primaryKey);

            $log = $transitionsTable->newEntity([
                'workflow_name' => $definition->getName(),
                'entity_table' => $definition->getTable(),
                'entity_id' => $id,
                'transition_name' => '_migrate',
                'from_state' => $oldState,
                'to_state' => $newState,
                'status' => TransitionLogger::STATUS_SUCCESS,
                'reason' => 'Version migration',
                'context' => ['type' => 'version_migration'],
                // Audit column holds the human workflow version, like normal transitions.
                'workflow_version' => (string)$definition->getVersion(),
            ]);
            $transitionsTable->saveOrFail($log);

            // Drop the old state's pending timeouts and schedule the target state's.
            $scheduler?->syncStateTimeouts($definition->getName(), $definition->getTable(), $entity, $newStateObj);
        }

        return count($entities);
    }

    /**
     * Whether workflow timeouts are enabled and their table is present.
     */
    private function timeoutsEnabled(Table $table): bool
    {
        if (!(bool)Configure::read('Workflow.timeouts', true)) {
            return false;
        }

        try {
            return in_array(
                'workflow_timeouts',
                $table->getConnection()->getSchemaCollection()->listTables(),
                true,
            );
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Distinct states present in the table that are no longer defined.
     *
     * @param \Cake\ORM\Table $table
     * @param string $field
     * @param array<string> $validStates
     *
     * @return array<string>
     */
    private function findOrphanStates(Table $table, string $field, array $validStates): array
    {
        /** @var array<\Cake\Datasource\EntityInterface> $rows */
        $rows = $table->find()
            ->select([$field])
            ->distinct([$field])
            ->whereNotInList($field, $validStates)
            ->toArray();

        $states = [];
        foreach ($rows as $row) {
            $state = $row->get($field);
            if ($state !== null && $state !== '') {
                $states[] = $state;
            }
        }

        return $states;
    }

    /**
     * Parse "old:new,old2:new2" into a map.
     *
     * @return array<string, string>
     */
    private function parseMap(string $raw): array
    {
        $map = [];
        if (trim($raw) === '') {
            return $map;
        }

        foreach (explode(',', $raw) as $pair) {
            if (!str_contains($pair, ':')) {
                continue;
            }
            [$old, $new] = explode(':', $pair, 2);
            $old = trim($old);
            $new = trim($new);
            if ($old !== '' && $new !== '') {
                $map[$old] = $new;
            }
        }

        return $map;
    }

    private function getRegistry(): WorkflowRegistry
    {
        $registry = WorkflowRegistryLocator::get() ?? Configure::read('Workflow.registry');
        if (!$registry instanceof WorkflowRegistry) {
            throw new RuntimeException('Workflow registry not configured');
        }

        return $registry;
    }
}
