<?php
declare(strict_types=1);

namespace Workflow\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Timeout
{
    public function __construct(
        public string $after,
        public string $transition,
    ) {
    }
}
