<?php

declare(strict_types=1);

namespace TestApp\Workflow\Order;

use Workflow\Attribute\Command;
use Workflow\Attribute\Guard;
use Workflow\Attribute\InitialState;
use Workflow\Attribute\Timeout;
use Workflow\Attribute\Transition;

#[InitialState]
#[Timeout('PT1H', 'pay')]
#[Transition(to: PaidState::class, name: 'pay', label: 'Capture payment')]
class PendingState extends BaseOrderState
{
    #[Guard('pay')]
    public function ensurePayable(): string|bool
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
