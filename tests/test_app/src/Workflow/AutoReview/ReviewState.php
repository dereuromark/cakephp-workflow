<?php

declare(strict_types=1);

namespace TestApp\Workflow\AutoReview;

use Workflow\Attribute\Condition;
use Workflow\Attribute\Transition;

#[Transition(to: ApprovedState::class, name: 'auto_approve', automatic: true)]
class ReviewState extends BaseAutoReviewState
{
    #[Condition('auto_approve')]
    public function isTrusted(): bool
    {
        return (bool)$this->getEntity()?->get('trusted');
    }
}
