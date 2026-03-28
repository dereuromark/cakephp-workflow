<?php
declare(strict_types=1);

namespace Workflow\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class OnEnter
{
    public function __construct()
    {
    }
}
