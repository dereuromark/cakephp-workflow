<?php

declare(strict_types=1);

namespace Workflow\Attribute;

use Attribute;

/**
 * Marks a state as a failed/error terminal state.
 * Failed states are final states that indicate an error condition.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class FailedState
{
}
