<?php

declare(strict_types=1);

namespace Workflow\Engine\Definition;

final class Transition
{
    /**
     * @param string $name Transition name
     * @param array<string> $from Source states
     * @param string $to Target state
     * @param bool $happy Whether this is a happy path transition
     * @param array<string> $guards Guard method names (block explicit transitions)
     * @param array<string> $commands Command method names
     * @param string|null $condition Condition name for automatic branching (evaluated without event trigger)
     * @param bool $automatic Whether this transition happens automatically without an event
     */
    public function __construct(
        private string $name,
        private array $from,
        private string $to,
        private bool $happy = false,
        private array $guards = [],
        private array $commands = [],
        private ?string $condition = null,
        private bool $automatic = false,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string>
     */
    public function getFrom(): array
    {
        return $this->from;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function isHappy(): bool
    {
        return $this->happy;
    }

    public function isAllowedFrom(string $state): bool
    {
        return in_array($state, $this->from, true);
    }

    /**
     * @return array<string>
     */
    public function getGuards(): array
    {
        return $this->guards;
    }

    /**
     * @return array<string>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Check if this transition has a condition for automatic branching.
     */
    public function hasCondition(): bool
    {
        return $this->condition !== null;
    }

    /**
     * Get the condition name for automatic branching.
     */
    public function getCondition(): ?string
    {
        return $this->condition;
    }

    /**
     * Check if this is an automatic transition (no event trigger required).
     *
     * Automatic transitions are evaluated when an entity enters a state,
     * and are used for branching logic based on conditions.
     */
    public function isAutomatic(): bool
    {
        return $this->automatic;
    }
}
