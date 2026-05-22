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
use Workflow\Engine\Definition\Definition;
use Workflow\Service\WorkflowRegistry;
use Workflow\Service\WorkflowRegistryLocator;

class WorkflowValidateCommand extends Command
{
    use LocatorAwareTrait;

    public static function defaultName(): string
    {
        return 'workflow validate';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Validate workflow definitions and detect issues')
            ->addArgument('name', [
                'help' => 'Workflow name (validates all if omitted)',
                'required' => false,
            ])
            ->addOption('check-data', [
                'boolean' => true,
                'help' => 'Check database for obsolete states (entities in undefined states)',
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $registry = $this->getRegistry();
        $name = $args->getArgument('name');
        $checkData = $args->getOption('check-data');

        $workflows = $name ? [$name] : $registry->getWorkflowNames();
        $hasErrors = false;

        foreach ($workflows as $workflowName) {
            $workflowHasErrors = false;

            if (!$registry->hasWorkflow($workflowName)) {
                $io->error("Workflow '{$workflowName}' not found.");
                $hasErrors = true;

                continue;
            }

            $definition = $registry->getWorkflow($workflowName);
            $io->out(sprintf('<info>Validating: %s</info>', $workflowName));

            // Check for unreachable states
            $unreachable = $this->findUnreachableStates($definition);
            if ($unreachable) {
                $io->warning('  Unreachable states (no incoming transitions):');
                foreach ($unreachable as $state) {
                    $io->out("    - <warning>{$state}</warning>");
                }
                $workflowHasErrors = true;
                $hasErrors = true;
            }

            // Check for dead-end states (non-final with no outgoing transitions)
            $deadEnds = $this->findDeadEndStates($definition);
            if ($deadEnds) {
                $io->warning('  Dead-end states (non-final with no outgoing transitions):');
                foreach ($deadEnds as $state) {
                    $io->out("    - <warning>{$state}</warning>");
                }
                $workflowHasErrors = true;
                $hasErrors = true;
            }

            // Check for missing initial state
            try {
                $definition->getInitialState();
            } catch (Exception $e) {
                $io->error('  No initial state defined!');
                $workflowHasErrors = true;
                $hasErrors = true;
            }

            // Check for orphaned transitions (to non-existent states)
            $orphanedTransitions = $this->findOrphanedTransitions($definition);
            if ($orphanedTransitions) {
                $io->error('  Transitions to non-existent states:');
                foreach ($orphanedTransitions as $t) {
                    $io->out("    - <error>{$t}</error>");
                }
                $workflowHasErrors = true;
                $hasErrors = true;
            }

            // Check for impossible transitions leaving terminal states
            $terminalOutgoingTransitions = $this->findOutgoingTransitionsFromTerminalStates($definition);
            if ($terminalOutgoingTransitions) {
                $io->error('  Transitions from terminal states:');
                foreach ($terminalOutgoingTransitions as $transition) {
                    $io->out("    - <error>{$transition}</error>");
                }
                $workflowHasErrors = true;
                $hasErrors = true;
            }

            // Check database for obsolete states
            if ($checkData) {
                $obsolete = $this->findObsoleteStates($definition);
                if ($obsolete) {
                    $io->warning('  Obsolete states found in database:');
                    foreach ($obsolete as $state => $count) {
                        $io->out("    - <warning>{$state}</warning> ({$count} entities)");
                    }
                    $workflowHasErrors = true;
                    $hasErrors = true;
                }
            }

            if (!$workflowHasErrors) {
                $io->success('  No issues found.');
            }

            $io->out('');
        }

        return $hasErrors ? self::CODE_ERROR : self::CODE_SUCCESS;
    }

    /**
     * Find states that cannot be reached from the initial state.
     *
     * @return array<string>
     */
    private function findUnreachableStates(Definition $definition): array
    {
        $reachable = [];
        $initial = $definition->getInitialState()->getName();
        $reachable[$initial] = true;

        // BFS to find all reachable states
        $queue = [$initial];
        while ((bool)$queue) {
            $current = array_shift($queue);
            $transitions = $definition->getTransitionsFromState($current);

            foreach ($transitions as $transition) {
                $to = $transition->getTo();
                if (!isset($reachable[$to])) {
                    $reachable[$to] = true;
                    $queue[] = $to;
                }
            }
        }

        // Find states not in reachable set
        $unreachable = [];
        foreach ($definition->getStates() as $state) {
            $name = $state->getName();
            if (!isset($reachable[$name])) {
                $unreachable[] = $name;
            }
        }

        return $unreachable;
    }

    /**
     * Find non-final states with no outgoing transitions.
     *
     * @return array<string>
     */
    private function findDeadEndStates(Definition $definition): array
    {
        $deadEnds = [];

        foreach ($definition->getStates() as $state) {
            if ($state->isFinal()) {
                continue;
            }

            $transitions = $definition->getTransitionsFromState($state->getName());
            if (!$transitions) {
                $deadEnds[] = $state->getName();
            }
        }

        return $deadEnds;
    }

    /**
     * Find transitions that reference non-existent states.
     *
     * @return array<string>
     */
    private function findOrphanedTransitions(Definition $definition): array
    {
        $orphaned = [];

        foreach ($definition->getTransitions() as $transition) {
            $to = $transition->getTo();
            if (!$definition->hasState($to)) {
                $orphaned[] = "{$transition->getName()} -> {$to}";
            }

            foreach ($transition->getFrom() as $from) {
                if (!$definition->hasState($from)) {
                    $orphaned[] = "{$from} -> {$transition->getName()}";
                }
            }
        }

        return $orphaned;
    }

    /**
     * Find transitions that start from final or failed states.
     *
     * @return array<string>
     */
    private function findOutgoingTransitionsFromTerminalStates(Definition $definition): array
    {
        $terminalOutgoingTransitions = [];

        foreach ($definition->getTransitions() as $transition) {
            foreach ($transition->getFrom() as $from) {
                if (!$definition->hasState($from)) {
                    continue;
                }

                $state = $definition->getState($from);
                if (!$state->isFinal()) {
                    continue;
                }

                $kind = $state->isFailed() ? 'failed' : 'final';
                $terminalOutgoingTransitions[] = "{$transition->getName()} from {$kind} state {$from}";
            }
        }

        return $terminalOutgoingTransitions;
    }

    /**
     * Find entities in states that don't exist in the definition.
     *
     * @return array<string, int>
     */
    private function findObsoleteStates(Definition $definition): array
    {
        $tableName = $definition->getTable();
        $field = $definition->getField();

        try {
            $table = $this->fetchTable($tableName);
        } catch (Exception $e) {
            return [];
        }

        $validStates = array_map(
            fn ($s) => $s->getName(),
            $definition->getStates(),
        );

        $query = $table->find()
            ->select([$field, 'count' => $table->find()->func()->count('*')])
            ->whereNotInList($field, $validStates)
            ->groupBy([$field]);

        $obsolete = [];
        /** @var array<\Cake\Datasource\EntityInterface> $rows */
        $rows = $query->toArray();
        foreach ($rows as $row) {
            $obsolete[$row->get($field)] = $row->get('count');
        }

        return $obsolete;
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
