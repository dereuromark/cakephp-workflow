<?php
declare(strict_types=1);

namespace Workflow\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Label
{
    public function __construct(
        public string $label,
    ) {
    }
}
