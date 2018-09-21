<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\Loop;
use PHPUnit\Framework\TestCase;

class InMemoryStreamTest extends TestCase
{
    public function testSingleReadConsumesEverything()
    {
        Loop::run(function () {
            $stream = new InMemoryStream("foobar");
            $this->assertSame("foobar", yield $stream->read());
            $this->assertNull(yield $stream->read());
        });
    }
}
