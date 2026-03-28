<?php

declare(strict_types=1);

namespace Workflow\Engine\Definition;

final class State
{
    /**
     * @param string $name
     * @param string|null $label
     * @param string|null $color
     * @param bool $initial
     * @param bool $final
     * @param bool $failed
     * @param array<string> $flags
     */
    public function __construct(
        private string $name,
        private ?string $label = null,
        private ?string $color = null,
        private bool $initial = false,
        private bool $final = false,
        private bool $failed = false,
        private array $flags = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getDisplayName(): string
    {
        return $this->label ?? $this->name;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function isInitial(): bool
    {
        return $this->initial;
    }

    public function isFinal(): bool
    {
        return $this->final || $this->failed;
    }

    /**
     * Check if this is a failed/error terminal state.
     */
    public function isFailed(): bool
    {
        return $this->failed;
    }

    /**
     * @return array<string>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags, true);
    }
}
