<?php

declare(strict_types=1);

namespace Workflow\Command;

use Cake\Core\Configure;
use Cake\Utility\Inflector;
use RuntimeException;

trait ScaffoldCommandTrait
{
    protected function defaultWorkflowNamespace(): string
    {
        $appNamespace = (string)(Configure::read('App.namespace') ?: 'App');

        return trim($appNamespace, '\\') . '\\Workflow';
    }

    protected function defaultWorkflowPath(): string
    {
        if (defined('APP')) {
            return rtrim(APP, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Workflow';
        }

        return getcwd() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Workflow';
    }

    protected function normalizeWorkflowSegment(string $name): string
    {
        return Inflector::camelize(Inflector::underscore(trim($name)));
    }

    protected function normalizeWorkflowName(string $name): string
    {
        return Inflector::underscore(trim($name));
    }

    protected function normalizeStateClass(string $name): string
    {
        $class = Inflector::camelize(Inflector::underscore(trim($name)));

        if (!str_ends_with($class, 'State')) {
            $class .= 'State';
        }

        return $class;
    }

    protected function writeScaffoldFile(string $path, string $contents, bool $force = false): void
    {
        if (file_exists($path) && !$force) {
            throw new RuntimeException(sprintf(
                'Refusing to overwrite existing file `%s`. Re-run with --force to replace it.',
                $path,
            ));
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create directory `%s`.', $directory));
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Failed to write file `%s`.', $path));
        }
    }
}
