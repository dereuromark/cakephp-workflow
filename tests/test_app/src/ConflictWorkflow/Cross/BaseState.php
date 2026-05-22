<?php

declare(strict_types=1);

namespace TestApp\ConflictWorkflow\Cross;

use Workflow\Attribute\StateMachine;
use Workflow\State\AbstractState;

#[StateMachine(name: 'cross_condition', table: 'Orders', field: 'state')]
abstract class BaseState extends AbstractState
{
}
