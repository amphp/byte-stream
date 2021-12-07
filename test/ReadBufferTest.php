<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;

final class ReadBufferTest extends AsyncTestCase
{
    public function testSingleReadConsumesEverything(): void
    {
        $stream = new ReadBuffer("foobar");
        self::assertSame("foobar", $stream->read());
        self::assertNull($stream->read());
    }

    public function testEmpty(): void
    {
        $stream = new ReadBuffer("");
        self::assertNull($stream->read());
    }
}
