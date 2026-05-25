<?php

declare(strict_types=1);

namespace Workflow\Command;

use Bake\Command\SimpleBakeCommand;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Utility\Inflector;

class BakeWorkflowStateCommand extends SimpleBakeCommand
{
    public string $pathFragment = 'Workflow/';

    protected string $_name;

    public static function defaultName(): string
    {
        return 'bake workflow_state';
    }

    public function bake(string $name, Arguments $args, ConsoleIo $io): void
    {
        $this->_name = $name;

        parent::bake($name, $args, $io);
    }

    public function bakeTest(string $name, Arguments $args, ConsoleIo $io): void
    {
        if ($args->getOption('no-test')) {
            return;
        }

        $className = $this->className($name);
        $namespace = (string)Configure::read('App.namespace');
        $plugin = (string)$args->getOption('plugin');
        if ($plugin) {
            $namespace = str_replace('/', '\\', $plugin);
        }

        $content = $this->generateTestContent($name, $namespace);
        $path = $plugin ? Plugin::path($plugin) : ROOT . DIRECTORY_SEPARATOR;
        $path .= 'tests/TestCase/Workflow/' . $this->subPath($name) . $className . 'Test.php';

        $io->createFile($path, $content, (bool)$args->getOption('force'));
    }

    public function template(): string
    {
        return 'WorkflowState/state';
    }

    public function templateData(Arguments $arguments): array
    {
        $name = $this->_name;
        $namespace = (string)Configure::read('App.namespace');
        $pluginPath = '';
        if ($this->plugin) {
            $namespace = $this->_pluginNamespace($this->plugin);
            $pluginPath = $this->plugin . '.';
        }

        $namespace .= '\\Workflow';
        $namespacePart = $this->namespacePart($name);
        if ($namespacePart !== null) {
            $namespace .= '\\' . $namespacePart;
        }

        $workflowSegment = $this->workflowSegment($name);
        $className = $this->className($name);
        $baseClass = (string)$arguments->getOption('base-class');
        if ($baseClass === '') {
            $baseClass = 'Base' . $workflowSegment . 'State';
        }

        $transitionTo = $arguments->getOption('transition-to');
        $transitionName = $arguments->getOption('transition-name');

        return [
            'plugin' => $this->plugin,
            'pluginPath' => $pluginPath,
            'namespace' => $namespace,
            'name' => $className,
            'baseClass' => $baseClass,
            'isInitial' => (bool)$arguments->getOption('initial'),
            'isFinal' => (bool)$arguments->getOption('final'),
            'isFailed' => (bool)$arguments->getOption('failed'),
            'transitionTo' => is_string($transitionTo) && $transitionTo !== '' ? $this->stateClass($transitionTo) : null,
            'transitionName' => is_string($transitionName) && $transitionName !== '' ? $transitionName : null,
        ];
    }

    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->setDescription('Bake a workflow state class file.')
            ->addOption('base-class', [
                'help' => 'Base workflow state class to extend. Defaults to Base<Workflow>State.',
                'default' => '',
            ])
            ->addOption('initial', [
                'boolean' => true,
                'help' => 'Mark the new state as the initial state.',
            ])
            ->addOption('final', [
                'boolean' => true,
                'help' => 'Mark the new state as a final state.',
            ])
            ->addOption('failed', [
                'boolean' => true,
                'help' => 'Mark the new state as a failed terminal state.',
            ])
            ->addOption('transition-to', [
                'help' => 'Target state class for a generated transition attribute.',
                'default' => '',
            ])
            ->addOption('transition-name', [
                'help' => 'Transition name to use together with --transition-to.',
                'default' => '',
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $transitionTo = (string)$args->getOption('transition-to');
        $transitionName = (string)$args->getOption('transition-name');
        if (($transitionTo === '') !== ($transitionName === '')) {
            $io->error('Use --transition-to and --transition-name together.');

            return static::CODE_ERROR;
        }

        $this->extractCommonProperties($args);
        if ($this->theme === null || $this->theme === '') {
            $this->theme = 'Workflow';
        }

        $name = $args->getArgumentAt(0);
        if (!$name) {
            $io->error('You must provide a name to bake a ' . $this->name());
            $this->abort();
        }

        $name = $this->_getName($name);
        $name = Inflector::camelize($name);
        $this->bake($name, $args, $io);
        $this->bakeTest($name, $args, $io);

        return static::CODE_SUCCESS;
    }

    public function name(): string
    {
        return 'workflow_state';
    }

    public function fileName(string $name): string
    {
        return $this->className($name) . '.php';
    }

    public function getPath(Arguments $args): string
    {
        $path = parent::getPath($args);
        $subPath = $this->subPath($this->_name);
        if ($subPath === '') {
            return $path;
        }

        return $path . $subPath;
    }

    private function generateTestContent(string $name, string $namespace): string
    {
        $className = $this->className($name);
        $subNamespace = $this->namespacePart($name);
        $taskClassNamespace = '\\Workflow';
        if ($subNamespace !== null) {
            $taskClassNamespace .= '\\' . $subNamespace;
        }

        $taskClass = $namespace . $taskClassNamespace . '\\' . $className;
        $testName = $className . 'Test';

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace}\Test\TestCase{$taskClassNamespace};

use Cake\TestSuite\TestCase;
use {$taskClass};

class {$testName} extends TestCase
{
    public function testStateName(): void
    {
        \$this->assertSame('{$this->stateName($className)}', {$className}::getStateName());
    }
}
PHP;
    }

    private function namespacePart(string $name): ?string
    {
        if (!str_contains($name, '/')) {
            return null;
        }

        $parts = explode('/', $name);
        array_pop($parts);

        return implode('\\', array_map($this->camelizeSegment(...), $parts));
    }

    private function subPath(string $name): string
    {
        if (!str_contains($name, '/')) {
            return '';
        }

        $parts = explode('/', $name);
        array_pop($parts);

        return implode(DIRECTORY_SEPARATOR, array_map($this->camelizeSegment(...), $parts)) . DIRECTORY_SEPARATOR;
    }

    private function className(string $name): string
    {
        $leaf = str_contains($name, '/') ? (string)substr($name, strrpos($name, '/') + 1) : $name;

        return $this->stateClass($leaf);
    }

    private function workflowSegment(string $name): string
    {
        $parts = explode('/', $name);

        return $this->camelizeSegment($parts[0]);
    }

    private function stateClass(string $name): string
    {
        $class = $this->camelizeSegment($name);
        if (!str_ends_with($class, 'State')) {
            $class .= 'State';
        }

        return $class;
    }

    private function stateName(string $className): string
    {
        return Inflector::underscore(substr($className, 0, -5));
    }

    private function camelizeSegment(string $segment): string
    {
        return Inflector::camelize(Inflector::underscore($segment));
    }
}
