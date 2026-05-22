<?php

declare(strict_types=1);

namespace Workflow\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\Locator\LocatorAwareTrait;
use RuntimeException;
use Throwable;
use Workflow\Service\WorkflowRegistry;
use Workflow\Service\WorkflowRegistryLocator;

/**
 * Apply a workflow transition to a single record from the command line.
 *
 * Useful for ops/debugging and for scripted/cron-driven transitions:
 *
 *   bin/cake workflow apply order 42 pay --reason "manual capture"
 */
class WorkflowApplyCommand extends Command
{
    use LocatorAwareTrait;

    public static function defaultName(): string
    {
        return 'workflow apply';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Apply a workflow transition to a single record')
            ->addArgument('workflow', ['help' => 'Workflow name, e.g. order', 'required' => true])
            ->addArgument('id', ['help' => 'Primary key of the record', 'required' => true])
            ->addArgument('transition', ['help' => 'Transition name to apply', 'required' => true])
            ->addOption('reason', ['help' => 'Reason recorded with the transition'])
            ->addOption('user', ['help' => 'User id recorded with the transition'])
            ->addOption('dry-run', ['boolean' => true, 'help' => 'Only check whether the transition is allowed']);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $workflow = (string)$args->getArgument('workflow');
        $id = (string)$args->getArgument('id');
        $transition = (string)$args->getArgument('transition');

        $registry = $this->getRegistry();
        if (!$registry->hasWorkflow($workflow)) {
            $io->error("Workflow '{$workflow}' not found.");

            return self::CODE_ERROR;
        }

        $definition = $registry->getWorkflow($workflow);
        $table = $this->fetchTable($definition->getTable());
        if (!$table->hasBehavior('Workflow')) {
            $io->error(sprintf("Table '%s' does not have the Workflow behavior attached.", $definition->getTable()));

            return self::CODE_ERROR;
        }

        try {
            $entity = $table->get($id);
        } catch (RecordNotFoundException $e) {
            $io->error(sprintf("Record #%s not found in '%s'.", $id, $definition->getTable()));

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
        $from = $behavior->getCurrentState($entity);

        $context = ['triggered_by' => 'cli'];
        $reason = $args->getOption('reason');
        if ($reason !== null) {
            $context['reason'] = (string)$reason;
        }
        $user = $args->getOption('user');
        if ($user !== null) {
            $context['user_id'] = (string)$user;
        }

        if ($args->getOption('dry-run')) {
            $allowed = $behavior->canTransition($entity, $transition, $context);
            $io->out(sprintf(
                'Dry run: transition "%s" from state "%s" of %s #%s is %s.',
                $transition,
                $from,
                $workflow,
                $id,
                $allowed ? '<success>allowed</success>' : '<warning>not allowed</warning>',
            ));

            return $allowed ? self::CODE_SUCCESS : self::CODE_ERROR;
        }

        try {
            $result = $behavior->transition($entity, $transition, $context);
        } catch (Throwable $e) {
            // Persistence failures, misconfigured guards/commands, DB errors etc. should
            // surface as a controlled non-zero exit, not an uncaught stack trace.
            $io->error('Error: ' . $e->getMessage());

            return self::CODE_ERROR;
        }

        if ($result->isSuccess()) {
            $io->success(sprintf('%s #%s: %s -> %s (%s).', $workflow, $id, $from, $result->getToState(), $transition));

            return self::CODE_SUCCESS;
        }
        if ($result->isBlocked()) {
            $io->warning(sprintf('Blocked: %s', json_encode($result->getBlockedBy())));

            return self::CODE_ERROR;
        }
        if ($result->isLocked()) {
            $io->warning('Locked: another process holds the lock for this record.');

            return self::CODE_ERROR;
        }

        $io->error('Error: ' . ($result->getError()?->getMessage() ?? 'unknown error'));

        return self::CODE_ERROR;
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
