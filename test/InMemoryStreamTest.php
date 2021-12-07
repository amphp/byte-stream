<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;

class InMemoryStreamTest extends AsyncTestCase
{
    public function testSingleReadConsumesEverything(): void
    {
        $stream = new InMemoryStream("foobar");
        self::assertSame("foobar", $stream->read());
        self::assertNull($stream->read());
    }
}
