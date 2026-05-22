<?php

declare(strict_types=1);

namespace TestApp\Workflow\AutoReview;

use Workflow\Attribute\FinalState;

#[FinalState]
class ApprovedState extends BaseAutoReviewState
{
}
