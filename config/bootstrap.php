<?php
declare(strict_types=1);

use Cake\Core\Configure;

$defaults = [
    'designer' => true,
    'logging' => true,
    'locking' => true,
    'timeouts' => true,
    'dynamicWorkflows' => false,
    'maxEventRepeats' => 10,
    'loader' => [
        'namespaces' => [],
        'configPath' => CONFIG . 'workflows' . DS,
    ],
    'versioning' => [
        'enabled' => true,
        'field' => 'state_workflow_version',
        'strictMode' => false,
    ],
    'multiTenancy' => [
        'enabled' => false,
    ],
];

$config = Configure::read('Workflow', []);
Configure::write('Workflow', array_merge($defaults, $config));
