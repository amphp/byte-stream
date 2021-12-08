<?php

namespace Amp\ByteStream;

use PHPUnit\Framework\TestCase;

final class PendingReadErrorTest extends TestCase
{
    public function testDefaultErrorCode(): void
    {
        self::assertSame(0, (new PendingReadError)->getCode());
    }
}
