<?php

declare(strict_types=1);

namespace Workflow\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
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

        $io->hr();
        $io->out(sprintf('Workflow namespace: %s', $namespace));
        $io->out(sprintf('Workflow name: %s', $workflowName));
        $io->out(sprintf('Suggested behavior config: $this->addBehavior(\'Workflow.Workflow\', [\'workflow\' => \'%s\']);', $workflowName));

        return self::CODE_SUCCESS;
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
