<?php

declare(strict_types=1);

namespace Workflow\Engine\Definition;

final class StateTimeout
{
    public function __construct(
        private string $after,
        private string $transition,
    ) {
    }

    public function getAfter(): string
    {
        return $this->after;
    }

    public function getTransition(): string
    {
        return $this->transition;
    }
}
