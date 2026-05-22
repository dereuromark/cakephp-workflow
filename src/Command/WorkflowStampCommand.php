<?php

declare(strict_types=1);

namespace Workflow\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;
use Exception;
use RuntimeException;
use Workflow\Service\WorkflowRegistry;
use Workflow\Service\WorkflowRegistryLocator;

/**
 * Backfill the definition version stamp onto existing records.
 *
 * Run once when enabling versioning on a table that already holds records, so
 * unversioned (NULL) rows are marked with the current definition hash.
 */
class WorkflowStampCommand extends Command
{
    use LocatorAwareTrait;
    use VersionFieldOptionTrait;

    public static function defaultName(): string
    {
        return 'workflow stamp';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Stamp the current definition version hash onto existing records.')
            ->addArgument('name', [
                'help' => 'Workflow name',
                'required' => true,
            ])
            ->addOption('version-field', [
                'help' => 'Entity column holding the version stamp '
                    . '(defaults to the behavior\'s configured field, else workflow_version)',
            ])
            ->addOption('all', [
                'boolean' => true,
                'help' => 'Re-stamp every record, not only unversioned (NULL) ones',
            ])
            ->addOption('dry-run', [
                'boolean' => true,
                'help' => 'Report how many records would be stamped without writing',
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

        $conditions = $args->getOption('all') ? [] : [$versionField . ' IS' => null];
        $count = $table->find()->where($conditions)->count();

        if ($args->getOption('dry-run')) {
            $io->out(sprintf('Would stamp %d record(s) with version <info>%s</info>.', $count, $hash));

            return self::CODE_SUCCESS;
        }

        if ($count > 0) {
            $table->updateAll([$versionField => $hash], $conditions);
        }

        $io->success(sprintf('Stamped %d record(s) with version %s.', $count, $hash));

        return self::CODE_SUCCESS;
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
