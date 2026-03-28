<?php

declare(strict_types=1);

namespace Workflow\Test\TestCase\Engine;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Workflow\Engine\TransitionResult;

class TransitionResultTest extends TestCase
{
    public function testSuccessResult(): void
    {
        $result = TransitionResult::success('pending', 'paid');

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isBlocked());
        $this->assertFalse($result->isLocked());
        $this->assertFalse($result->isError());
        $this->assertSame('pending', $result->getFromState());
        $this->assertSame('paid', $result->getToState());
        $this->assertEmpty($result->getBlockedBy());
        $this->assertNull($result->getError());
    }

    public function testBlockedResult(): void
    {
        $blockedBy = [
            'checkPayment' => 'Payment not received',
            'checkStock' => 'Out of stock',
        ];
        $result = TransitionResult::blocked('pending', $blockedBy);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isBlocked());
        $this->assertFalse($result->isLocked());
        $this->assertFalse($result->isError());
        $this->assertSame('pending', $result->getFromState());
        $this->assertNull($result->getToState());
        $this->assertSame($blockedBy, $result->getBlockedBy());
        $this->assertNull($result->getError());
    }

    public function testLockedResult(): void
    {
        $result = TransitionResult::locked('pending');

        $this->assertFalse($result->isSuccess());
        $this->assertFalse($result->isBlocked());
        $this->assertTrue($result->isLocked());
        $this->assertFalse($result->isError());
        $this->assertSame('pending', $result->getFromState());
        $this->assertNull($result->getToState());
        $this->assertEmpty($result->getBlockedBy());
        $this->assertNull($result->getError());
    }

    public function testErrorResult(): void
    {
        $error = new RuntimeException('Command failed');
        $result = TransitionResult::error('pending', $error);

        $this->assertFalse($result->isSuccess());
        $this->assertFalse($result->isBlocked());
        $this->assertFalse($result->isLocked());
        $this->assertTrue($result->isError());
        $this->assertSame('pending', $result->getFromState());
        $this->assertNull($result->getToState());
        $this->assertEmpty($result->getBlockedBy());
        $this->assertSame($error, $result->getError());
    }
}
