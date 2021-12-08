<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;

final class BufferTest extends AsyncTestCase
{
    public function testBuffer(): void
    {
        $stream = new IterableStream(["abc", "def", "g"]);

        self::assertSame("abcdefg", buffer($stream));
    }
}
