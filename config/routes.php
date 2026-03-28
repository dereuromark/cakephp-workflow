<?php

declare(strict_types=1);

use Cake\Routing\RouteBuilder;

// Admin routes: /admin/workflow/*
$routes->prefix('Admin', function (RouteBuilder $routes): void {
    $routes->plugin('Workflow', function (RouteBuilder $routes): void {
        $routes->connect('/', ['controller' => 'Workflows', 'action' => 'index']);
        $routes->connect('/workflows', ['controller' => 'Workflows', 'action' => 'index']);
        $routes->connect('/workflows/view/{name}', ['controller' => 'Workflows', 'action' => 'view'])
            ->setPass(['name']);
        $routes->connect('/items', ['controller' => 'Items', 'action' => 'index']);
        $routes->connect('/items/view/{table}/{id}', ['controller' => 'Items', 'action' => 'view'])
            ->setPass(['table', 'id']);
        $routes->connect('/transitions', ['controller' => 'Transitions', 'action' => 'index']);
        $routes->connect('/designer', ['controller' => 'Designer', 'action' => 'index']);
        $routes->connect('/designer/{name}', ['controller' => 'Designer', 'action' => 'edit'])
            ->setPass(['name']);
        $routes->fallbacks();
    });
});
