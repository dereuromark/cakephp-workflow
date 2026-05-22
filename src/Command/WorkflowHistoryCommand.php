<?php

declare(strict_types=1);

namespace Workflow\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;
use RuntimeException;
use Workflow\Service\WorkflowRegistry;
use Workflow\Service\WorkflowRegistryLocator;

/**
 * Show the transition audit trail for a single record:
 *
 *   bin/cake workflow history order 42
 */
class WorkflowHistoryCommand extends Command
{
    use LocatorAwareTrait;

    public static function defaultName(): string
    {
        return 'workflow history';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Show the transition history for a single record')
            ->addArgument('workflow', ['help' => 'Workflow name, e.g. order', 'required' => true])
            ->addArgument('id', ['help' => 'Primary key of the record', 'required' => true])
            ->addOption('limit', ['help' => 'Maximum rows to show', 'default' => '50'])
            ->addOption('success', ['boolean' => true, 'help' => 'Only successful transitions']);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $workflow = (string)$args->getArgument('workflow');
        $id = (string)$args->getArgument('id');

        $registry = $this->getRegistry();
        if (!$registry->hasWorkflow($workflow)) {
            $io->error("Workflow '{$workflow}' not found.");

            return self::CODE_ERROR;
        }

        $definition = $registry->getWorkflow($workflow);
        $transitionsTable = $this->fetchTable('Workflow.WorkflowTransitions');

        /** @var array<\Workflow\Model\Entity\WorkflowTransition> $rows */
        $rows = $transitionsTable
            ->find(
                'forEntity',
                workflow: $workflow,
                table: $definition->getTable(),
                id: $id,
                successOnly: (bool)$args->getOption('success'),
            )
            ->limit((int)$args->getOption('limit'))
            ->all()
            ->toArray();

        if (!$rows) {
            $io->out(sprintf('No transition history for %s #%s.', $workflow, $id));

            return self::CODE_SUCCESS;
        }

        $table = [['When', 'Transition', 'From', 'To', 'Status', 'User', 'Reason']];
        foreach ($rows as $row) {
            $table[] = [
                (string)$row->get('created'),
                (string)$row->get('transition_name'),
                (string)$row->get('from_state'),
                (string)$row->get('to_state'),
                (string)$row->get('status'),
                (string)($row->get('user_id') ?? ''),
                (string)($row->get('reason') ?? ''),
            ];
        }

        $io->helper('Table')->output($table);

        return self::CODE_SUCCESS;
    }

    protected function getRegistry(): WorkflowRegistry
    {
        $registry = WorkflowRegistryLocator::get() ?? Configure::read('Workflow.registry');
        if (!$registry instanceof WorkflowRegistry) {
            throw new RuntimeException('Workflow registry not configured');
        }

        return $registry;
    }
}
