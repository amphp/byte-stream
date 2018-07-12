<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use PHPUnit\Framework\TestCase;

class InMemoryStreamTest extends TestCase
{
    public function testSingleReadConsumesEverything(): void
    {
        $stream = new InMemoryStream("foobar");
        $this->assertSame("foobar", $stream->read());
        $this->assertNull($stream->read());
    }
}
