<?php

declare(strict_types=1);

namespace Workflow\Engine\Definition;

final readonly class State
{
    /**
     * @param string $name
     * @param string|null $label
     * @param string|null $color
     * @param bool $initial
     * @param bool $final
     * @param bool $failed
     * @param array<string> $flags
     * @param array<string> $onEnter Callback names to invoke when entering this state
     * @param array<string> $onExit Callback names to invoke when exiting this state
     * @param array<string> $requireReasonFor Transition names that require a reason when leaving this state
     * @param array<\Workflow\Engine\Definition\StateTimeout> $timeouts Time-based transitions to schedule while in this state
     * @param bool $unknown True for synthetic placeholder states representing a value not defined in the workflow
     */
    public function __construct(
        private string $name,
        private ?string $label = null,
        private ?string $color = null,
        private bool $initial = false,
        private bool $final = false,
        private bool $failed = false,
        private array $flags = [],
        private array $onEnter = [],
        private array $onExit = [],
        private array $requireReasonFor = [],
        private array $timeouts = [],
        private bool $unknown = false,
    ) {
    }

    /**
     * Create a synthetic placeholder for a state value that is not defined in the
     * workflow (e.g. an orphaned record left behind after a definition change).
     *
     * Such a state is non-initial, non-final and carries no transitions, so reads
     * and display degrade gracefully instead of throwing.
     */
    public static function unknown(string $name): self
    {
        return new self(name: $name, color: '#6c757d', unknown: true);
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

    /**
     * Whether this is a synthetic placeholder for a value not defined in the workflow.
     */
    public function isUnknown(): bool
    {
        return $this->unknown;
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

    /**
     * Get callback names to invoke when entering this state.
     *
     * @return array<string>
     */
    public function getOnEnter(): array
    {
        return $this->onEnter;
    }

    /**
     * Get callback names to invoke when exiting this state.
     *
     * @return array<string>
     */
    public function getOnExit(): array
    {
        return $this->onExit;
    }

    /**
     * Get transition names that require a reason when leaving this state.
     *
     * @return array<string>
     */
    public function getRequireReasonFor(): array
    {
        return $this->requireReasonFor;
    }

    /**
     * Check if a specific transition requires a reason.
     */
    public function requiresReasonFor(string $transition): bool
    {
        return in_array($transition, $this->requireReasonFor, true);
    }

    /**
     * @return array<\Workflow\Engine\Definition\StateTimeout>
     */
    public function getTimeouts(): array
    {
        return $this->timeouts;
    }

    public function hasTimeouts(): bool
    {
        return $this->timeouts !== [];
    }
}
