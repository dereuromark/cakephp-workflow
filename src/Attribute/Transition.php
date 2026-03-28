<?php
declare(strict_types=1);

namespace Workflow\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Transition
{
    /**
     * @param class-string $to Target state class
     */
    public function __construct(
        public string $to,
        public string $name,
        public bool $happy = false,
    ) {
    }
}
