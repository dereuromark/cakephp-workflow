<?php

declare(strict_types=1);

use Cake\Routing\RouteBuilder;

// Admin routes: /admin/workflow/*
$routes->prefix('Admin', function (RouteBuilder $routes): void {
    $routes->plugin('Workflow', function (RouteBuilder $routes): void {
        $routes->connect('/', ['controller' => 'Workflow', 'action' => 'index']);
        $routes->connect('/workflows', ['controller' => 'Workflows', 'action' => 'index']);
        $routes->connect('/workflows/create', ['controller' => 'Workflows', 'action' => 'create']);
        $routes->connect('/workflows/view/{name}', ['controller' => 'Workflows', 'action' => 'view'])
            ->setPass(['name']);
        $routes->connect('/transitions', ['controller' => 'Transitions', 'action' => 'index']);
        $routes->connect('/locks', ['controller' => 'Locks', 'action' => 'index']);
        $routes->connect('/locks/release/{id}', ['controller' => 'Locks', 'action' => 'release'])
            ->setPass(['id']);
        $routes->connect('/locks/cleanup', ['controller' => 'Locks', 'action' => 'cleanup']);
        $routes->connect('/timeouts', ['controller' => 'Timeouts', 'action' => 'index']);
        $routes->connect('/timeouts/cancel/{id}', ['controller' => 'Timeouts', 'action' => 'cancel'])
            ->setPass(['id']);
        $routes->fallbacks();
    });
});
