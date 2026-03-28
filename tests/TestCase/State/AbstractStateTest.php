<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\State;

use PHPUnit\Framework\TestCase;
use Workflow\State\AbstractState;

class AbstractStateTest extends TestCase
{
    public function testClassNameToStateName(): void
    {
        // Simple case
        $this->assertSame('pending', AbstractState::classNameToStateName('PendingState'));

        // Multi-word case
        $this->assertSame('in_progress', AbstractState::classNameToStateName('InProgressState'));

        // Without State suffix
        $this->assertSame('pending', AbstractState::classNameToStateName('Pending'));

        // Multiple uppercase letters
        $this->assertSame('order_processing', AbstractState::classNameToStateName('OrderProcessingState'));

        // Single word
        $this->assertSame('active', AbstractState::classNameToStateName('ActiveState'));

        // Already lowercase
        $this->assertSame('simple', AbstractState::classNameToStateName('simple'));
    }

    public function testClassNameToStateNameEdgeCases(): void
    {
        // Empty string
        $this->assertSame('', AbstractState::classNameToStateName(''));

        // Just "State" suffix
        $this->assertSame('', AbstractState::classNameToStateName('State'));

        // Numbers (regex only handles letter transitions, not number-to-letter)
        $this->assertSame('step1complete', AbstractState::classNameToStateName('Step1CompleteState'));

        // All caps word - "State" suffix is removed first, then lowercased
        $this->assertSame('api', AbstractState::classNameToStateName('APIState'));
    }
}
