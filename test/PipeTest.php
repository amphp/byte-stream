<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\IterableStream;
use Amp\ByteStream\OutputBuffer;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;
use function Amp\ByteStream\pipe;

class PipeTest extends AsyncTestCase
{
    public function testPipe()
    {
        $stream = new IterableStream(Pipeline\fromIterable(["abc", "def"]));
        $buffer = new OutputBuffer;

        self::assertSame(6, pipe($stream, $buffer));

        $buffer->end();
        self::assertSame("abcdef", $buffer->buffer());
    }
}
