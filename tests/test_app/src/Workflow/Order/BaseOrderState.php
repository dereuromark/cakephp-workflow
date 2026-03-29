<?php

declare(strict_types=1);

namespace TestApp\Workflow\Order;

use Workflow\Attribute\StateMachine;
use Workflow\State\AbstractState;

#[StateMachine(name: 'attribute_order', table: 'Orders', field: 'state')]
abstract class BaseOrderState extends AbstractState
{
}
