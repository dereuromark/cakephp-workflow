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
     * @param int $version Workflow version number (increment when making breaking changes)
     */
    public function __construct(
        public string $name,
        public string $table,
        public string $field = 'state',
        public ?array $states = null,
        public int $version = 1,
    ) {
    }
}
