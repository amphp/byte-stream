<?php


namespace Amp\ByteStream\Test;

use Amp\ByteStream\IteratorStream;
use Amp\Iterator;
use Amp\PHPUnit\TestCase;
use function Amp\ByteStream\buffer;
use function Amp\Promise\wait;

class BufferTest extends TestCase
{
    public function testBuffer()
    {
        $stream = new IteratorStream(Iterator\fromIterable(["abc", "def", "g"], 10));

        $this->assertSame("abcdefg", wait(buffer($stream)));
    }
}
