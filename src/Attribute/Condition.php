<?php

declare(strict_types=1);

namespace Workflow\Attribute;

use Attribute;

/**
 * Marks a state method as the condition for an automatic transition. The method
 * receives the entity (and optionally the context) and returns bool; the automatic
 * transition is only taken when it returns true.
 *
 * ```php
 * #[Transition(to: ApprovedState::class, name: 'auto_approve', automatic: true)]
 * class ReviewState extends OrderState
 * {
 *     #[Condition('auto_approve')]
 *     public function isTrusted(): bool
 *     {
 *         return (bool)$this->getEntity()?->get('trusted');
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Condition
{
    public function __construct(public string $transition)
    {
    }
}
