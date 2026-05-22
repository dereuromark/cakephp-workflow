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
use Workflow\Service\TimeoutScheduler;
use Workflow\Service\TransitionLogger;
use Workflow\Service\WorkflowRegistry;
use Workflow\Service\WorkflowRegistryLocator;

class WorkflowTimeoutsCommand extends Command
{
    use LocatorAwareTrait;

    public static function defaultName(): string
    {
        return 'workflow timeouts';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Process pending workflow timeouts')
            ->addOption('limit', [
                'short' => 'l',
                'help' => 'Maximum number of timeouts to process',
                'default' => '100',
            ])
            ->addOption('dry-run', [
                'boolean' => true,
                'help' => 'Show what would be processed without executing',
            ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $limit = (int)$args->getOption('limit');
        $dryRun = $args->getOption('dry-run');

        $timeoutsTable = $this->fetchTable('Workflow.WorkflowTimeouts');
        $registry = $this->getRegistry();

        /** @var array<\Workflow\Model\Entity\WorkflowTimeout> $pendingTimeouts */
        $pendingTimeouts = $timeoutsTable->find('due', limit: $limit)->toArray();

        if (!$pendingTimeouts) {
            $io->success('No pending timeouts to process.');

            return self::CODE_SUCCESS;
        }

        $io->out(sprintf('Found %d pending timeouts.', count($pendingTimeouts)));

        if ($dryRun) {
            $io->warning('Dry run mode - no changes will be made.');
        }

        $processed = 0;
        $errors = 0;

        foreach ($pendingTimeouts as $timeout) {
            $io->out(sprintf(
                'Processing: %s/%s entity #%s - %s',
                $timeout->workflow_name,
                $timeout->entity_table,
                $timeout->entity_id,
                $timeout->transition_name,
            ));

            if ($dryRun) {
                $processed++;

                continue;
            }

            try {
                // Get workflow definition first to know the correct field name
                $definition = $registry->getWorkflow($timeout->workflow_name);
                $field = $definition->getField();

                // Load the entity
                $entityTable = $this->fetchTable($timeout->entity_table);
                $entity = $entityTable->get($timeout->entity_id);

                // Verify entity is still in expected state
                if ($entity->get($field) !== $timeout->current_state) {
                    $io->warning(sprintf(
                        '  Entity state changed from %s to %s, skipping.',
                        $timeout->current_state,
                        $entity->get($field),
                    ));
                    $timeout->processed = true;
                    $timeoutsTable->save($timeout);

                    continue;
                }

                $context = [
                    'triggered_by' => 'timeout',
                    'timeout_id' => $timeout->id,
                ];

                // Prefer the behaviour so the transition goes through applyTransition():
                // it sets the internal marker (so beforeSave's "no direct state change"
                // guard does not reject the save) and handles save + log + lock + timeout
                // sync consistently. Without this, the raw engine + saveOrFail below is
                // rejected by the behaviour's beforeSave on any entity table that uses it.
                if ($entityTable->hasBehavior('Workflow')) {
                    /** @var \Workflow\Model\Behavior\WorkflowBehavior $behavior */
                    $behavior = $entityTable->getBehavior('Workflow');
                    $result = $behavior->transition($entity, $timeout->transition_name, $context);

                    if ($result->isSuccess()) {
                        $timeout->processed = true;
                        $timeoutsTable->saveOrFail($timeout);
                        $processed++;
                        $io->success('  Transition applied and logged successfully.');
                    } else {
                        $io->warning('  Transition blocked: ' . json_encode($result->getBlockedBy()));
                        $errors++;
                    }

                    continue;
                }

                // Fallback: entity table without the Workflow behaviour (no beforeSave guard).
                $engine = $registry->getEngine($timeout->workflow_name);
                $connection = $entityTable->getConnection();
                $result = null;

                $success = $connection->transactional(function () use (
                    $engine,
                    $definition,
                    $entity,
                    $timeout,
                    $entityTable,
                    $timeoutsTable,
                    $context,
                    $field,
                    &$result,
                ): bool {
                    $result = $engine->apply($definition, $entity, $timeout->transition_name, $context);

                    if (!$result->isSuccess()) {
                        return false;
                    }

                    // Save entity
                    $entityTable->saveOrFail($entity);

                    // Log the transition
                    $logger = new TransitionLogger();
                    $logger->log(
                        $timeout->workflow_name,
                        $timeout->entity_table,
                        $entity,
                        $result,
                        $timeout->transition_name,
                        $context,
                        (string)$definition->getVersion(),
                    );

                    // Mark timeout as processed
                    $timeout->processed = true;
                    $timeoutsTable->saveOrFail($timeout);

                    $scheduler = new TimeoutScheduler();
                    $scheduler->syncStateTimeouts(
                        $timeout->workflow_name,
                        $timeout->entity_table,
                        $entity,
                        $definition->getState((string)$entity->get($field)),
                    );

                    return true;
                });

                if ($success) {
                    $processed++;
                    $io->success('  Transition applied and logged successfully.');
                } else {
                    $blockedBy = $result !== null ? $result->getBlockedBy() : ['unknown' => 'Transaction failed'];
                    $io->warning('  Transition blocked: ' . json_encode($blockedBy));
                    $errors++;
                }
            } catch (Exception $e) {
                $io->error('  Error: ' . $e->getMessage());
                $errors++;
            }
        }

        $io->hr();
        $io->out(sprintf('Processed: %d, Errors: %d', $processed, $errors));

        return $errors > 0 ? self::CODE_ERROR : self::CODE_SUCCESS;
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
