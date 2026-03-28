<?php

declare(strict_types=1);

namespace Workflow\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Command
{
    public function __construct(public string $transition)
    {
    }
}
