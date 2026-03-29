<?php

declare(strict_types=1);

namespace Workflow\Service;

use Cake\Core\ContainerInterface;
use Throwable;

class WorkflowRegistryLocator
{
    private static ?ContainerInterface $container = null;

    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    public static function get(): ?WorkflowRegistry
    {
        if (self::$container === null || !self::$container->has(WorkflowRegistry::class)) {
            return null;
        }

        try {
            $registry = self::$container->get(WorkflowRegistry::class);
        } catch (Throwable) {
            return null;
        }

        if (!$registry instanceof WorkflowRegistry) {
            return null;
        }

        return $registry;
    }
}
