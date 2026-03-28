<?php
declare(strict_types=1);

namespace Workflow\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Workflow\Renderer\MermaidRenderer;
use Workflow\Service\WorkflowRegistry;

class WorkflowShowCommand extends Command
{
    public static function defaultName(): string
    {
        return 'workflow show';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Show details of a specific workflow')
            ->addArgument('name', [
                'help' => 'Workflow name',
                'required' => true,
            ])
            ->addOption('mermaid', [
                'short' => 'm',
                'boolean' => true,
                'help' => 'Output Mermaid diagram',
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        /** @var string $name */
        $name = $args->getArgument('name');
        $registry = $this->getRegistry();

        if (!$registry->hasWorkflow($name)) {
            $io->error("Workflow '{$name}' not found.");

            return self::CODE_ERROR;
        }

        $definition = $registry->getWorkflow($name);

        if ($args->getOption('mermaid')) {
            $renderer = new MermaidRenderer();
            $io->out($renderer->render($definition));

            return self::CODE_SUCCESS;
        }

        $io->out(sprintf('<info>Workflow: %s</info>', $definition->getName()));
        $io->out(sprintf('Table: %s', $definition->getTable()));
        $io->out(sprintf('Field: %s', $definition->getField()));
        $io->out(sprintf('Version Hash: %s', $definition->getVersionHash()));
        $io->hr();

        $io->out('<info>States:</info>');
        foreach ($definition->getStates() as $state) {
            $flags = [];
            if ($state->isInitial()) {
                $flags[] = '<success>initial</success>';
            }
            if ($state->isFinal()) {
                $flags[] = '<comment>final</comment>';
            }
            foreach ($state->getFlags() as $flag) {
                $flags[] = "<info>{$flag}</info>";
            }
            $flagStr = !empty($flags) ? ' [' . implode(', ', $flags) . ']' : '';

            $io->out(sprintf('  %s%s', $state->getName(), $flagStr));
            if ($state->getLabel()) {
                $io->out(sprintf('    Label: %s', $state->getLabel()));
            }
            if ($state->getColor()) {
                $io->out(sprintf('    Color: %s', $state->getColor()));
            }
        }

        $io->hr();
        $io->out('<info>Transitions:</info>');
        foreach ($definition->getTransitions() as $transition) {
            $happy = $transition->isHappy() ? ' <success>★</success>' : '';
            $io->out(sprintf(
                '  %s%s: %s → %s',
                $transition->getName(),
                $happy,
                implode('|', $transition->getFrom()),
                $transition->getTo(),
            ));

            if (!empty($transition->getGuards())) {
                $io->out(sprintf('    Guards: %s', implode(', ', $transition->getGuards())));
            }
            if (!empty($transition->getCommands())) {
                $io->out(sprintf('    Commands: %s', implode(', ', $transition->getCommands())));
            }
        }

        return self::CODE_SUCCESS;
    }

    private function getRegistry(): WorkflowRegistry
    {
        return Configure::read('Workflow.registry');
    }
}
