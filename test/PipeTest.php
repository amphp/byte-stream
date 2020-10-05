<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\OutputBuffer;
use Amp\Iterator;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\await;
use function Amp\ByteStream\pipe;

class PipeTest extends AsyncTestCase
{
    public function testPipe()
    {
        $stream = new IteratorStream(Iterator\fromIterable(["abc", "def"]));
        $buffer = new OutputBuffer;

        $this->assertSame(6, await(pipe($stream, $buffer)));

        $buffer->end();
        $this->assertSame("abcdef", await($buffer));
    }
}
