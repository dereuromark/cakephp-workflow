<?php

declare(strict_types=1);

namespace TestApp\ConflictWorkflow\Cross;

use Workflow\Attribute\Condition;
use Workflow\Attribute\InitialState;
use Workflow\Attribute\Transition;

#[InitialState]
#[Transition(to: BState::class, name: 'move')]
#[Transition(to: EndState::class, name: 'go', automatic: true)]
class AState extends BaseState
{
    #[Condition('go')]
    public function fromA(): bool
    {
        return true;
    }
}
