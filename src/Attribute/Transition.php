<?php

declare(strict_types=1);

namespace Workflow\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Transition
{
    /**
     * @param class-string $to Target state class
     * @param string $name Transition name
     * @param bool $happy Whether this is a happy path transition
     */
    public function __construct(
        public string $to,
        public string $name,
        public bool $happy = false,
    ) {
    }
}
