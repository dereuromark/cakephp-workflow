<?php

declare(strict_types=1);

namespace TestApp\ConflictWorkflow\Dup;

use Workflow\Attribute\StateMachine;
use Workflow\State\AbstractState;

#[StateMachine(name: 'dup_condition', table: 'Orders', field: 'state')]
abstract class BaseState extends AbstractState
{
}
