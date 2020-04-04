<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\IteratorStream;
use Amp\Iterator;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\ByteStream\buffer;

class BufferTest extends AsyncTestCase
{
    public function testBuffer()
    {
        $stream = new IteratorStream(Iterator\fromIterable(["abc", "def", "g"], 10));

        $this->assertSame("abcdefg", yield buffer($stream));
    }
}
