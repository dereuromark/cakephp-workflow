<?php

declare(strict_types=1);

namespace TestApp\Workflow\Order;

use Workflow\Attribute\Command;
use Workflow\Attribute\Guard;
use Workflow\Attribute\InitialState;
use Workflow\Attribute\Transition;

#[InitialState]
#[Transition(to: PaidState::class, name: 'pay')]
class PendingState extends BaseOrderState
{
    #[Guard('pay')]
    public function ensurePayable(): bool|string
    {
        if ((float)$this->getEntity()?->get('total') <= 0.0) {
            return 'Total must be positive';
        }

        return true;
    }

    #[Command('pay')]
    public function markCommandRan(): void
    {
        $this->getEntity()?->set('command_ran', true);
    }
}
