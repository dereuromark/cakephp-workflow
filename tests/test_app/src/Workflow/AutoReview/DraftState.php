<?php

declare(strict_types=1);

namespace TestApp\Workflow\AutoReview;

use Workflow\Attribute\InitialState;
use Workflow\Attribute\Transition;

#[InitialState]
#[Transition(to: ReviewState::class, name: 'submit')]
class DraftState extends BaseAutoReviewState
{
}
