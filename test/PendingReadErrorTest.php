<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\PendingReadError;
use Amp\PHPUnit\TestCase;

class PendingReadErrorTest extends TestCase
{
    public function testDefaultErrorCode()
    {
        $this->assertSame(0, (new PendingReadError)->getCode());
    }
}
