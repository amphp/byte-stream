<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\PipelineStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;
use function Amp\ByteStream\buffer;

class BufferTest extends AsyncTestCase
{
    public function testBuffer()
    {
        $stream = new PipelineStream(Pipeline\fromIterable(["abc", "def", "g"], 10));

        $this->assertSame("abcdefg", buffer($stream));
    }
}
