<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;

final class ReadableBufferTest extends AsyncTestCase
{
    public function testSingleReadConsumesEverything(): void
    {
        $stream = new ReadableBuffer("foobar");
        self::assertSame("foobar", $stream->read());
        self::assertNull($stream->read());
    }

    public function testEmpty(): void
    {
        $stream = new ReadableBuffer("");
        self::assertNull($stream->read());
    }
}
