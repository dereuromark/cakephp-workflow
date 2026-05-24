<?php

declare(strict_types=1);

namespace Workflow\Exception;

class TransitionBlockedException extends WorkflowException
{
    /**
     * @var array<string, string>
     */
    private readonly array $blockedBy;

    /**
     * @param string $transition
     * @param array<string, string> $blockedBy
     */
    public function __construct(string $transition, array $blockedBy)
    {
        $this->blockedBy = $blockedBy;
        $guards = implode(', ', array_keys($blockedBy));
        parent::__construct("Transition '{$transition}' blocked by: {$guards}");
    }

    /**
     * @return array<string, string>
     */
    public function getBlockedBy(): array
    {
        return $this->blockedBy;
    }
}
