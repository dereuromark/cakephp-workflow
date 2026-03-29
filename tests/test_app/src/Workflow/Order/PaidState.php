<?php

declare(strict_types=1);

namespace TestApp\Workflow\Order;

use Workflow\Attribute\FinalState;
use Workflow\Attribute\OnEnter;

#[FinalState]
class PaidState extends BaseOrderState
{
    #[OnEnter]
    public function markEntered(): void
    {
        $this->getEntity()?->set('entered_paid', true);
    }
}
