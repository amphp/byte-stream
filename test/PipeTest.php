<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\OutputBuffer;
use Amp\ByteStream\PipelineStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;
use function Amp\await;
use function Amp\ByteStream\pipe;

class PipeTest extends AsyncTestCase
{
    public function testPipe()
    {
        $stream = new PipelineStream(Pipeline\fromIterable(["abc", "def"]));
        $buffer = new OutputBuffer;

        self::assertSame(6, await(pipe($stream, $buffer)));

        $buffer->end();
        self::assertSame("abcdef", await($buffer));
    }
}
