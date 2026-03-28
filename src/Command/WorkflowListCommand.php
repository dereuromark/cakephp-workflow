<?php
declare(strict_types=1);

namespace Workflow\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Workflow\Service\WorkflowRegistry;

class WorkflowListCommand extends Command
{
    public static function defaultName(): string
    {
        return 'workflow list';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('List all configured workflows')
            ->addOption('verbose', [
                'short' => 'v',
                'boolean' => true,
                'help' => 'Show detailed information',
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $registry = $this->getRegistry();
        $names = $registry->getWorkflowNames();

        if (empty($names)) {
            $io->warning('No workflows configured.');

            return self::CODE_SUCCESS;
        }

        $io->out('Configured Workflows:');
        $io->hr();

        foreach ($names as $name) {
            $definition = $registry->getWorkflow($name);

            $io->out(sprintf('<info>%s</info>', $name));
            $io->out(sprintf('  Table: %s', $definition->getTable()));
            $io->out(sprintf('  Field: %s', $definition->getField()));
            $io->out(sprintf('  States: %d', count($definition->getStates())));
            $io->out(sprintf('  Transitions: %d', count($definition->getTransitions())));

            if ($args->getOption('verbose')) {
                $io->out('  State list:');
                foreach ($definition->getStates() as $state) {
                    $markers = [];
                    if ($state->isInitial()) {
                        $markers[] = 'initial';
                    }
                    if ($state->isFinal()) {
                        $markers[] = 'final';
                    }
                    $markerStr = !empty($markers) ? ' (' . implode(', ', $markers) . ')' : '';
                    $io->out(sprintf('    - %s%s', $state->getName(), $markerStr));
                }
            }

            $io->out('');
        }

        return self::CODE_SUCCESS;
    }

    private function getRegistry(): WorkflowRegistry
    {
        return Configure::read('Workflow.registry');
    }
}
