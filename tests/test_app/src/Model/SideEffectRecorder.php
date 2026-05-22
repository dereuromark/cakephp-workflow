<?php

declare(strict_types=1);

namespace TestApp\Model;

/**
 * Test helper: records command executions so tests can assert a command did NOT run
 * on a lost optimistic claim.
 */
class SideEffectRecorder
{
    public static int $count = 0;

    public static function record(): void
    {
        self::$count++;
    }
}
