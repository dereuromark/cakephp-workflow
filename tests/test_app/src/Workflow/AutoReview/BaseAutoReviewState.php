<?php

declare(strict_types=1);

namespace TestApp\Workflow\AutoReview;

use Workflow\Attribute\StateMachine;
use Workflow\State\AbstractState;

#[StateMachine(name: 'auto_review', table: 'Orders', field: 'state')]
abstract class BaseAutoReviewState extends AbstractState
{
}
