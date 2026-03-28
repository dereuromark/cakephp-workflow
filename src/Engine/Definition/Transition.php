<?php
declare(strict_types=1);

namespace Workflow\Engine\Definition;

final class Transition
{
    /**
     * @param array<string> $from
     * @param array<string> $guards
     * @param array<string> $commands
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
