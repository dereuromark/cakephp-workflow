<?php

declare(strict_types=1);

namespace Workflow\Renderer;

use Workflow\Engine\Definition\Definition;

interface RendererInterface
{
    /**
     * Render a workflow definition to a string format.
     */
    public function render(Definition $definition): string;
}
