<?php
declare(strict_types=1);

use Cake\Core\Configure;

$defaults = [
    'designer' => true,
    'logging' => true,
    'locking' => true,
    'timeouts' => true,
    'lockDuration' => 30,
    'maxEventRepeats' => 10,
    'strictMode' => false,
    'loader' => [
        'namespaces' => [],
        'configPath' => CONFIG . 'workflows' . DS,
        'pathMap' => [],
    ],
];

$config = Configure::read('Workflow', []);
Configure::write('Workflow', array_replace_recursive($defaults, $config));
