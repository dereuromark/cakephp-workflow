<?php

declare(strict_types=1);

namespace Workflow\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class StateMachine
{
    /**
     * @param string $name
     * @param string $table
     * @param string $field
     * @param array<string, class-string>|null $states Optional state name overrides
     */
    public function __construct(
        public string $name,
        public string $table,
        public string $field = 'state',
        public ?array $states = null,
    ) {
    }
}
