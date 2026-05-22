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
use Workflow\Service\WorkflowBatchService;
use Workflow\Service\WorkflowRegistry;
use Workflow\Service\WorkflowRegistryLocator;

/**
 * Apply a transition to many records at once — for mass-advancing records and for
 * scheduled batch jobs:
 *
 *   bin/cake workflow batch order pay --state pending --limit 500
 */
class WorkflowBatchCommand extends Command
{
    use LocatorAwareTrait;

    public static function defaultName(): string
    {
        return 'workflow batch';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Apply a transition to all records currently in a given state')
            ->addArgument('workflow', ['help' => 'Workflow name, e.g. order', 'required' => true])
            ->addArgument('transition', ['help' => 'Transition name to apply', 'required' => true])
            ->addOption('state', ['help' => 'Only records currently in this state', 'required' => true])
            ->addOption('limit', ['help' => 'Maximum records to process'])
            ->addOption('reason', ['help' => 'Reason recorded with each transition'])
            ->addOption('user', ['help' => 'User id recorded with each transition'])
            ->addOption('stop-on-failure', ['boolean' => true, 'help' => 'Stop at the first failed transition'])
            ->addOption('dry-run', ['boolean' => true, 'help' => 'Only count the matching records']);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $workflow = (string)$args->getArgument('workflow');
        $transition = (string)$args->getArgument('transition');
        $fromState = (string)$args->getOption('state');

        $registry = $this->getRegistry();
        if (!$registry->hasWorkflow($workflow)) {
            $io->error("Workflow '{$workflow}' not found.");

            return self::CODE_ERROR;
        }

        $definition = $registry->getWorkflow($workflow);
        /** @var \Cake\ORM\Table&\Workflow\Model\WorkflowTableInterface $table */
        $table = $this->fetchTable($definition->getTable());
        if (!$table->hasBehavior('Workflow')) {
            $io->error(sprintf("Table '%s' does not have the Workflow behavior attached.", $definition->getTable()));

            return self::CODE_ERROR;
        }
        /** @var \Workflow\Model\Behavior\WorkflowBehavior $behavior */
        $behavior = $table->getBehavior('Workflow');
        if ($behavior->getConfig('workflow') !== $workflow) {
            $io->error(sprintf(
                "Table '%s' has a Workflow behavior for '%s', not '%s'.",
                $definition->getTable(),
                (string)$behavior->getConfig('workflow'),
                $workflow,
            ));

            return self::CODE_ERROR;
        }

        $limit = $args->getOption('limit') !== null ? (int)$args->getOption('limit') : null;

        if ($args->getOption('dry-run')) {
            $query = $table->find()->where([$table->aliasField($definition->getField()) => $fromState]);
            if ($limit !== null) {
                $query->limit($limit);
            }
            $io->out(sprintf(
                'Dry run: %d record(s) in state "%s" would get transition "%s".',
                $query->count(),
                $fromState,
                $transition,
            ));

            return self::CODE_SUCCESS;
        }

        $context = ['triggered_by' => 'cli-batch'];
        $reason = $args->getOption('reason');
        if ($reason !== null) {
            $context['reason'] = (string)$reason;
        }
        $user = $args->getOption('user');
        if ($user !== null) {
            $context['user_id'] = (string)$user;
        }

        $result = (new WorkflowBatchService())->applyToState(
            $table,
            $fromState,
            $transition,
            $context,
            $limit,
            (bool)$args->getOption('stop-on-failure'),
        );

        $io->out(sprintf(
            'Processed %d record(s): %d succeeded, %d failed.',
            $result->getTotal(),
            $result->getSuccessCount(),
            $result->getFailureCount(),
        ));

        if ($result->getFailureCount() > 0) {
            $io->warning('Some transitions did not succeed (blocked/locked/error).');

            return self::CODE_ERROR;
        }

        $io->success('Done.');

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
