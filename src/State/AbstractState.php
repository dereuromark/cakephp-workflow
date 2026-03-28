<?php

declare(strict_types=1);

namespace Workflow\State;

use ReflectionClass;

abstract class AbstractState
{
    /**
     * Get the state name derived from the class name.
     * Removes "State" suffix and converts to snake_case.
     */
    public static function getStateName(): string
    {
        $className = (new ReflectionClass(static::class))->getShortName();

        return self::classNameToStateName($className);
    }

    /**
     * Convert a class name to a state name.
     * "PendingState" -> "pending"
     * "InProgressState" -> "in_progress"
     */
    public static function classNameToStateName(string $className): string
    {
        // Remove "State" suffix if present
        if (str_ends_with($className, 'State')) {
            $className = substr($className, 0, -5);
        }

        // Convert PascalCase to snake_case
        $snake = (string)preg_replace('/([a-z])([A-Z])/', '$1_$2', $className);

        return strtolower($snake);
    }
}
