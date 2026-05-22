<?php

declare(strict_types=1);

namespace Workflow\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Utility\Inflector;
use RuntimeException;

class WorkflowInitCommand extends Command
{
    use ScaffoldCommandTrait;

    public static function defaultName(): string
    {
        return 'workflow init';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Scaffold an attribute-based workflow with base, initial, and final state classes')
            ->addArgument('name', [
                'help' => 'Workflow name, e.g. order or order_return',
                'required' => true,
            ])
            ->addArgument('table', [
                'help' => 'Cake table alias, e.g. Orders',
                'required' => true,
            ])
            ->addOption('field', [
                'help' => 'Entity field used to store the current state',
                'default' => 'state',
            ])
            ->addOption('namespace', [
                'help' => 'Base namespace for workflow classes',
            ])
            ->addOption('path', [
                'help' => 'Base filesystem path for generated workflow classes',
            ])
            ->addOption('initial', [
                'help' => 'Initial state class name',
                'default' => 'Pending',
            ])
            ->addOption('final', [
                'help' => 'Final state class name',
                'default' => 'Completed',
            ])
            ->addOption('force', [
                'boolean' => true,
                'help' => 'Overwrite existing files if they already exist',
            ])
            ->addOption('migration', [
                'boolean' => true,
                'help' => 'Also scaffold a migration that adds the state column to the table',
            ])
            ->addOption('migrations-path', [
                'help' => 'Directory for the generated migration (default: config/Migrations)',
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $workflowInput = (string)$args->getArgument('name');
        $table = (string)$args->getArgument('table');
        $field = (string)$args->getOption('field');
        $force = (bool)$args->getOption('force');

        $workflowSegment = $this->normalizeWorkflowSegment($workflowInput);
        $workflowName = $this->normalizeWorkflowName($workflowInput);
        $initialStateClass = $this->normalizeStateClass((string)$args->getOption('initial'));
        $finalStateClass = $this->normalizeStateClass((string)$args->getOption('final'));

        if ($initialStateClass === $finalStateClass) {
            throw new RuntimeException('Initial and final state classes must be different.');
        }

        $baseNamespace = rtrim((string)($args->getOption('namespace') ?: $this->defaultWorkflowNamespace()), '\\');
        $basePath = rtrim((string)($args->getOption('path') ?: $this->defaultWorkflowPath()), DIRECTORY_SEPARATOR);

        $namespace = $baseNamespace . '\\' . $workflowSegment;
        $directory = $basePath . DIRECTORY_SEPARATOR . $workflowSegment;
        $baseStateClass = 'Base' . $workflowSegment . 'State';
        $transitionName = $this->normalizeWorkflowName($finalStateClass);
        $transitionName = substr($transitionName, 0, -strlen('_state'));

        $files = [
            $directory . DIRECTORY_SEPARATOR . $baseStateClass . '.php' => $this->renderBaseState(
                $namespace,
                $baseStateClass,
                $workflowName,
                $table,
                $field,
            ),
            $directory . DIRECTORY_SEPARATOR . $initialStateClass . '.php' => $this->renderInitialState(
                $namespace,
                $baseStateClass,
                $initialStateClass,
                $finalStateClass,
                $transitionName,
            ),
            $directory . DIRECTORY_SEPARATOR . $finalStateClass . '.php' => $this->renderFinalState(
                $namespace,
                $baseStateClass,
                $finalStateClass,
            ),
        ];

        foreach ($files as $path => $contents) {
            $this->writeScaffoldFile($path, $contents, $force);
            $io->out(sprintf('Created %s', $path));
        }

        if ($args->getOption('migration')) {
            $migrationsPath = rtrim(
                (string)($args->getOption('migrations-path') ?: $this->defaultMigrationsPath()),
                DIRECTORY_SEPARATOR,
            );
            $migrationClass = 'Add' . Inflector::camelize($field) . 'ColumnTo' . $table;
            $migrationFile = $migrationsPath . DIRECTORY_SEPARATOR . date('YmdHis') . '_' . $migrationClass . '.php';
            $this->writeScaffoldFile(
                $migrationFile,
                $this->renderMigration($migrationClass, $table, $field, $workflowName),
                $force,
            );
            $io->out(sprintf('Created %s', $migrationFile));
        }

        $io->hr();
        $io->out(sprintf('Workflow namespace: %s', $namespace));
        $io->out(sprintf('Workflow name: %s', $workflowName));
        $io->out('Next steps:');
        $io->out(sprintf(
            '  1. Add the behavior + trait to %s:',
            $table . 'Table',
        ));
        $io->out('       use Workflow\Model\Table\WorkflowTableTrait;');
        $io->out(sprintf(
            '       $this->addBehavior(\'Workflow.Workflow\', [\'workflow\' => \'%s\']);',
            $workflowName,
        ));
        $io->out('  2. Add WorkflowTrait to the entity for $entity->currentState()/canTransition().');
        if (!$args->getOption('migration')) {
            $io->out('  3. Add a "' . $field . '" column to the table (re-run with --migration to scaffold it).');
        }
        $io->out('  4. Render actions in a view: $this->Workflow->panel($definition, $entity, $transitions, [...]).');

        return self::CODE_SUCCESS;
    }

    /**
     * Default location for generated migrations (the app's config/Migrations).
     */
    private function defaultMigrationsPath(): string
    {
        return (defined('CONFIG') ? CONFIG : ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR)
            . 'Migrations';
    }

    /**
     * Render a Migrations file that adds the workflow state column to the table.
     */
    private function renderMigration(string $migrationClass, string $table, string $field, string $workflowName): string
    {
        $tableName = Inflector::underscore($table);

        return <<<PHP
<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class {$migrationClass} extends BaseMigration
{
    public function change(): void
    {
        \$this->table('{$tableName}')
            ->addColumn('{$field}', 'string', [
                'limit' => 64,
                'null' => true,
                'default' => null,
                'comment' => 'Workflow state ({$workflowName})',
            ])
            ->addIndex(['{$field}'])
            ->update();
    }
}
PHP;
    }

    private function renderBaseState(
        string $namespace,
        string $baseStateClass,
        string $workflowName,
        string $table,
        string $field,
    ): string {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Workflow\Attribute\StateMachine;
use Workflow\State\AbstractState;

#[StateMachine(name: '{$workflowName}', table: '{$table}', field: '{$field}')]
abstract class {$baseStateClass} extends AbstractState
{
}
PHP;
    }

    private function renderInitialState(
        string $namespace,
        string $baseStateClass,
        string $initialStateClass,
        string $finalStateClass,
        string $transitionName,
    ): string {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Workflow\Attribute\InitialState;
use Workflow\Attribute\Transition;

#[InitialState]
#[Transition(to: {$finalStateClass}::class, name: '{$transitionName}', happy: true)]
class {$initialStateClass} extends {$baseStateClass}
{
}
PHP;
    }

    private function renderFinalState(
        string $namespace,
        string $baseStateClass,
        string $finalStateClass,
    ): string {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Workflow\Attribute\FinalState;

#[FinalState]
class {$finalStateClass} extends {$baseStateClass}
{
}
PHP;
    }
}
