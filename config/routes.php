<?php
declare(strict_types=1);

use Cake\Routing\RouteBuilder;

/** @var \Cake\Routing\RouteBuilder $routes */
$routes->plugin('Workflow', ['path' => '/admin/workflow'], function (RouteBuilder $builder): void {
    $builder->prefix('Admin', function (RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Workflows', 'action' => 'index']);
        $builder->connect('/workflows', ['controller' => 'Workflows', 'action' => 'index']);
        $builder->connect('/workflows/view/{name}', ['controller' => 'Workflows', 'action' => 'view'])
            ->setPass(['name']);
        $builder->connect('/items', ['controller' => 'Items', 'action' => 'index']);
        $builder->connect('/items/view/{table}/{id}', ['controller' => 'Items', 'action' => 'view'])
            ->setPass(['table', 'id']);
        $builder->connect('/transitions', ['controller' => 'Transitions', 'action' => 'index']);
        $builder->connect('/designer', ['controller' => 'Designer', 'action' => 'index']);
        $builder->connect('/designer/{name}', ['controller' => 'Designer', 'action' => 'edit'])
            ->setPass(['name']);
        $builder->fallbacks();
    });
});
