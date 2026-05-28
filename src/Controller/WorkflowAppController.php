<?php

declare(strict_types=1);

namespace Workflow\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Workflow\Service\WorkflowRegistry;
use Workflow\Service\WorkflowRegistryLocator;

class WorkflowAppController extends Controller
{
    protected ?WorkflowRegistry $workflowRegistry = null;

    public function initialize(): void
    {
        parent::initialize();

        $registry = WorkflowRegistryLocator::get() ?? Configure::read('Workflow.registry');
        if ($registry instanceof WorkflowRegistry) {
            $this->workflowRegistry = $registry;
        }
    }
}
