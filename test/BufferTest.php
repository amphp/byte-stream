<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\IterableStream;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\ByteStream\buffer;

class BufferTest extends AsyncTestCase
{
    public function testBuffer(): void
    {
        $stream = new IterableStream(["abc", "def", "g"]);

        self::assertSame("abcdefg", buffer($stream));
    }
}
