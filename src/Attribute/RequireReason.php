<?php

declare(strict_types=1);

namespace Workflow\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class RequireReason
{
    /**
     * @param array<string> $for Transition names that require a reason
     */
    public function __construct(public array $for)
    {
    }
}
