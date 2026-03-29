<?php

declare(strict_types=1);

namespace Workflow\State;

use Cake\Datasource\EntityInterface;
use ReflectionClass;

abstract class AbstractState
{
    protected ?EntityInterface $entity = null;

    /**
     * @var array<string, mixed>
     */
    protected array $context = [];

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
        $result = preg_replace('/([a-z])([A-Z])/', '$1_$2', $className);
        if ($result === null) {
            // Fallback to lowercase class name if regex fails
            return strtolower($className);
        }

        return strtolower($result);
    }

    /**
     * Bind the current transition context to this state instance.
     *
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array<string, mixed> $context
     */
    public function bind(EntityInterface $entity, array $context = []): static
    {
        $this->entity = $entity;
        $this->context = $context;

        return $this;
    }

    public function getEntity(): ?EntityInterface
    {
        return $this->entity;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
