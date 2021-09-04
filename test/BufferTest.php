<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\PipelineStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;
use function Amp\ByteStream\buffer;

class BufferTest extends AsyncTestCase
{
    public function testBuffer(): void
    {
        $stream = new PipelineStream(Pipeline\fromIterable(["abc", "def", "g"], 0.01));

        self::assertSame("abcdefg", buffer($stream));
    }
}
