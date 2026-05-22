<?php

declare(strict_types=1);

namespace TestApp\ConflictWorkflow\Dup;

use Workflow\Attribute\FinalState;

#[FinalState]
class EndState extends BaseState
{
}
