<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;

final class PipeTest extends AsyncTestCase
{
    public function testPipe(): void
    {
        $stream = new IterableStream(Pipeline\fromIterable(["abc", "def"]));
        $buffer = new WritableBuffer;

        self::assertSame(6, pipe($stream, $buffer));

        $buffer->end();
        self::assertSame("abcdef", $buffer->buffer());
    }
}
