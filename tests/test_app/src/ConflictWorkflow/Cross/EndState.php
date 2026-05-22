<?php

declare(strict_types=1);

namespace TestApp\ConflictWorkflow\Cross;

use Workflow\Attribute\FinalState;

#[FinalState]
class EndState extends BaseState
{
}
