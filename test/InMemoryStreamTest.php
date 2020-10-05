<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\PHPUnit\AsyncTestCase;

class InMemoryStreamTest extends AsyncTestCase
{
    public function testSingleReadConsumesEverything()
    {
        $stream = new InMemoryStream("foobar");
        $this->assertSame("foobar", $stream->read());
        $this->assertNull($stream->read());
    }
}
