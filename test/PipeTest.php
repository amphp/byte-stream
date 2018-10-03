<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\OutputBuffer;
use Amp\Iterator;
use Amp\PHPUnit\TestCase;
use function Amp\ByteStream\pipe;
use function Amp\Promise\wait;

class PipeTest extends TestCase
{
    public function testPipe()
    {
        $stream = new IteratorStream(Iterator\fromIterable(["abc", "def"]));
        $buffer = new OutputBuffer;

        $this->assertSame(6, wait(pipe($stream, $buffer)));

        $buffer->end();
        $this->assertSame("abcdef", wait($buffer));
    }
}
