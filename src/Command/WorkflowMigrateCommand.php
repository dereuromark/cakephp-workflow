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
 * Move orphaned records forward after a workflow definition changes.
 *
 * Orphaned records are those whose stored state no longer exists in the current
 * definition. Each is moved to a mapped target state, logged as a transition, and
 * (when enabled) has its timeouts resynced. The command refuses to run when any
 * orphaned state has no mapping, so no record is left behind or silently lost.
 *
 * The headless counterpart to the admin Orphans view.
 */
class WorkflowMigrateCommand extends Command
{
    use LocatorAwareTrait;

    public static function defaultName(): string
    {
        return 'workflow migrate';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Move orphaned records forward to valid states after a definition change.')
            ->addArgument('name', [
                'help' => 'Workflow name',
                'required' => true,
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

        try {
            $table = $this->fetchTable($definition->getTable());
        } catch (Exception $e) {
            $io->error("Could not load table '{$definition->getTable()}': " . $e->getMessage());

            return self::CODE_ERROR;
        }

        $field = $definition->getField();
        $validStates = array_map(fn ($s) => $s->getName(), $definition->getStates());
        $map = $this->parseMap((string)$args->getOption('map'));

        foreach ($map as $old => $new) {
            if (!in_array($new, $validStates, true)) {
                $io->error("Mapping target '{$new}' is not a defined state in workflow '{$name}'.");

                return self::CODE_ERROR;
            }
        }

        $orphanStates = $this->findOrphanStates($table, $field, $validStates);
        if (!$orphanStates) {
            $io->success('No orphaned records found.');

            return self::CODE_SUCCESS;
        }

        $unmapped = array_values(array_filter($orphanStates, fn ($state) => !isset($map[$state])));
        if ($unmapped) {
            $io->error('Orphaned states without a mapping: ' . implode(', ', $unmapped));
            $io->out('Provide a mapping for each, e.g. --map ' . $unmapped[0] . ':' . ($validStates[0] ?? 'target'));

            return self::CODE_ERROR;
        }

        if ($args->getOption('dry-run')) {
            foreach ($orphanStates as $state) {
                $count = $table->find()->where([$field => $state])->count();
                $io->out(sprintf("[dry-run] Would migrate %d record(s) from '%s' to '%s'.", $count, $state, $map[$state]));
            }

            return self::CODE_SUCCESS;
        }

        // Apply migrations atomically: if any audit-log write fails, the whole batch
        // rolls back so no record is changed without its log entry. This assumes the
        // audit/timeout tables share the entity table's connection, as elsewhere in
        // the plugin (TransitionLogger / TimeoutScheduler).
        $syncTimeouts = $this->timeoutsEnabled($table);
        $migrated = [];
        try {
            $table->getConnection()->transactional(
                function () use ($table, $definition, $field, $orphanStates, $map, $syncTimeouts, &$migrated): void {
                    foreach ($orphanStates as $state) {
                        $migrated[$state] = $this->migrateState($table, $definition, $field, $state, $map[$state], $syncTimeouts);
                    }
                },
            );
        } catch (Exception $e) {
            $io->error('Migration aborted (no changes applied): ' . $e->getMessage());

            return self::CODE_ERROR;
        }

        foreach ($migrated as $state => $count) {
            $io->success(sprintf("Migrated %d record(s) from '%s' to '%s'.", $count, $state, $map[$state]));
        }

        return self::CODE_SUCCESS;
    }

    /**
     * Move every record in $oldState to $newState, log a transition, and (when enabled)
     * resync the target state's timeouts.
     *
     * @return int Number of records migrated
     */
    private function migrateState(
        Table $table,
        Definition $definition,
        string $field,
        string $oldState,
        string $newState,
        bool $syncTimeouts,
    ): int {
        // Only the id is needed for logging and timeout syncing; selecting just that
        // column keeps memory bounded. The id convention matches the rest of the plugin.
        /** @var array<\Cake\Datasource\EntityInterface> $entities */
        $entities = $table->find()->select(['id'])->where([$field => $oldState])->toArray();
        if (!$entities) {
            return 0;
        }

        $table->updateAll([$field => $newState], [$field => $oldState]);

        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');
        $newStateObj = $definition->getState($newState);
        $scheduler = $syncTimeouts ? new TimeoutScheduler() : null;

        foreach ($entities as $entity) {
            $id = (string)$entity->get('id');

            $log = $transitionsTable->newEntity([
                'workflow_name' => $definition->getName(),
                'model' => $definition->getTable(),
                'foreign_key' => $id,
                'transition_name' => '_migrate',
                'from_state' => $oldState,
                'to_state' => $newState,
                'status' => TransitionLogger::STATUS_SUCCESS,
                'reason' => 'Orphan migration',
                'context' => ['type' => 'orphan_migration'],
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
            ->all()
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
