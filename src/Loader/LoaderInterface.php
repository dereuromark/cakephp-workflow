<?php
declare(strict_types=1);

namespace Workflow\Loader;

use Workflow\Engine\Definition\Definition;

interface LoaderInterface
{
    /**
     * Check if this loader supports the given workflow name.
     */
    public function supports(string $workflowName): bool;

    /**
     * Load a workflow definition by name.
     */
    public function load(string $workflowName): Definition;

    /**
     * Get all workflow names this loader can provide.
     *
     * @return array<string>
     */
    public function getWorkflowNames(): array;
}
