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
     * @param array<string> $guards Guard method names
     * @param array<string> $commands Command method names
     */
    public function __construct(
        private string $name,
        private array $from,
        private string $to,
        private bool $happy = false,
        private array $guards = [],
        private array $commands = [],
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
}
