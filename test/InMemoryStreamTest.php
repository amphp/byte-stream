<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\StringBufferStream;
use PHPUnit\Framework\TestCase;

class InMemoryStreamTest extends TestCase
{
    public function testSingleReadConsumesEverything(): void
    {
        $stream = new StringBufferStream("foobar");
        $this->assertSame("foobar", $stream->read());
        $this->assertNull($stream->read());
    }
}
