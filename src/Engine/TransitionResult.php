<?php
declare(strict_types=1);

namespace Workflow\Engine;

use Throwable;

final class TransitionResult
{
    private const STATUS_SUCCESS = 'success';
    private const STATUS_BLOCKED = 'blocked';
    private const STATUS_LOCKED = 'locked';
    private const STATUS_ERROR = 'error';

    /**
     * @param array<string, string> $blockedBy
     */
    private function __construct(
        private string $status,
        private string $fromState,
        private ?string $toState = null,
        private array $blockedBy = [],
        private ?Throwable $error = null,
    ) {
    }

    public static function success(string $fromState, string $toState): self
    {
        return new self(self::STATUS_SUCCESS, $fromState, $toState);
    }

    /**
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
}
