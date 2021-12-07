<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;

class PipeTest extends AsyncTestCase
{
    public function testPipe()
    {
        $stream = new IterableStream(Pipeline\fromIterable(["abc", "def"]));
        $buffer = new WriteBuffer;

        self::assertSame(6, pipe($stream, $buffer));

        $buffer->end();
        self::assertSame("abcdef", $buffer->buffer());
    }
}
