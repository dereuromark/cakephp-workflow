<?php

declare(strict_types=1);

namespace TestApp\ConflictWorkflow\Dup;

use Workflow\Attribute\Condition;
use Workflow\Attribute\InitialState;
use Workflow\Attribute\Transition;

#[InitialState]
#[Transition(to: EndState::class, name: 'go', automatic: true)]
class StartState extends BaseState
{
    #[Condition('go')]
    public function first(): bool
    {
        return true;
    }

    #[Condition('go')]
    public function second(): bool
    {
        return false;
    }
}
