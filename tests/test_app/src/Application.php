<?php

declare(strict_types=1);

namespace TestApp;

use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\RouteBuilder;

class Application extends BaseApplication
{
    public function bootstrap(): void
    {
        if (class_exists(\Bake\BakePlugin::class)) {
            $this->addPlugin('Bake');
        }

        $this->addPlugin('Workflow');
    }

    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue->add(new RoutingMiddleware($this));
    }

    public function routes(RouteBuilder $routes): void
    {
        // Admin routes for the Workflow plugin
        $routes->prefix('Admin', function (RouteBuilder $routes): void {
            $routes->plugin('Workflow', function (RouteBuilder $routes): void {
                $routes->fallbacks();
            });
        });
    }
}
