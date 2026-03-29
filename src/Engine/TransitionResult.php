<?php

declare(strict_types=1);

namespace Workflow\Engine;

use Throwable;

final class TransitionResult
{
    /**
     * @var string
     */
    private const STATUS_SUCCESS = 'success';

    /**
     * @var string
     */
    private const STATUS_BLOCKED = 'blocked';

    /**
     * @var string
     */
    private const STATUS_LOCKED = 'locked';

    /**
     * @var string
     */
    private const STATUS_ERROR = 'error';

    /**
     * @param string $status
     * @param string $fromState
     * @param string|null $toState
     * @param array<string, string> $blockedBy Guards that blocked the transition (name => reason)
     * @param \Throwable|null $error
     * @param array<string> $guardsEvaluated Guards that were evaluated
     * @param array<string> $commandsExecuted Commands that were executed
     * @param bool $usedLock Whether a lock was acquired for this transition
     */
    private function __construct(
        private string $status,
        private string $fromState,
        private ?string $toState = null,
        private array $blockedBy = [],
        private ?Throwable $error = null,
        private array $guardsEvaluated = [],
        private array $commandsExecuted = [],
        private bool $usedLock = false,
    ) {
    }

    /**
     * @param string $fromState
     * @param string $toState
     * @param array<string> $guardsEvaluated
     * @param array<string> $commandsExecuted
     * @param bool $usedLock
     */
    public static function success(
        string $fromState,
        string $toState,
        array $guardsEvaluated = [],
        array $commandsExecuted = [],
        bool $usedLock = false,
    ): self {
        return new self(
            self::STATUS_SUCCESS,
            $fromState,
            $toState,
            [],
            null,
            $guardsEvaluated,
            $commandsExecuted,
            $usedLock,
        );
    }

    /**
     * @param string $fromState
     * @param array<string, string> $blockedBy
     */
    public static function blocked(string $fromState, array $blockedBy): self
    {
        return new self(self::STATUS_BLOCKED, $fromState, null, $blockedBy);
    }

    public static function locked(string $fromState): self
    {
        return new self(self::STATUS_LOCKED, $fromState);
    }

    public static function error(string $fromState, Throwable $error): self
    {
        return new self(self::STATUS_ERROR, $fromState, null, [], $error);
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    public function getFromState(): string
    {
        return $this->fromState;
    }

    public function getToState(): ?string
    {
        return $this->toState;
    }

    /**
     * @return array<string, string>
     */
    public function getBlockedBy(): array
    {
        return $this->blockedBy;
    }

    public function getError(): ?Throwable
    {
        return $this->error;
    }

    /**
     * @return array<string>
     */
    public function getGuardsEvaluated(): array
    {
        return $this->guardsEvaluated;
    }

    /**
     * @return array<string>
     */
    public function getCommandsExecuted(): array
    {
        return $this->commandsExecuted;
    }

    public function usedLock(): bool
    {
        return $this->usedLock;
    }
}
