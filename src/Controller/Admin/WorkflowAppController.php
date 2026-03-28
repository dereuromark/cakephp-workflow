<?php

declare(strict_types=1);

namespace Workflow\Controller\Admin;

use Cake\Controller\Controller;
use Cake\Event\EventInterface;

class WorkflowAppController extends Controller
{
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
        $this->viewBuilder()->setLayout('Workflow.workflow');
    }

    public function beforeRender(EventInterface $event): void
    {
        parent::beforeRender($event);

        $this->viewBuilder()->addHelpers(['Workflow.Workflow']);
    }
}
