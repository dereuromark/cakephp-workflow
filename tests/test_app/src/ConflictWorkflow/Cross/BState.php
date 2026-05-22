<?php

declare(strict_types=1);

namespace TestApp\ConflictWorkflow\Cross;

use Workflow\Attribute\Condition;
use Workflow\Attribute\Transition;

#[Transition(to: EndState::class, name: 'go', automatic: true)]
class BState extends BaseState
{
    #[Condition('go')]
    public function fromB(): bool
    {
        return false;
    }
}
