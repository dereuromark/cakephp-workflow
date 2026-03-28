<?php
declare(strict_types=1);

namespace Workflow\Loader;

use Workflow\Engine\Definition\Definition;
use Workflow\Exception\WorkflowException;

class ChainLoader implements LoaderInterface
{
    /**
     * @param array<LoaderInterface> $loaders Loaders in priority order (first = highest)
     */
    public function __construct(
        private array $loaders,
    ) {
    }

    public function supports(string $workflowName): bool
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($workflowName)) {
                return true;
            }
        }

        return false;
    }

    public function load(string $workflowName): Definition
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($workflowName)) {
                return $loader->load($workflowName);
            }
        }

        throw new WorkflowException("Workflow '{$workflowName}' not found in any loader");
    }

    /**
     * @return array<string>
     */
    public function getWorkflowNames(): array
    {
        $names = [];
        foreach ($this->loaders as $loader) {
            $names = array_merge($names, $loader->getWorkflowNames());
        }

        return array_unique($names);
    }

    /**
     * Add a loader to the chain.
     */
    public function addLoader(LoaderInterface $loader): void
    {
        $this->loaders[] = $loader;
    }
}
