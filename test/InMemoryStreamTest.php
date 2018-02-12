<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use function Amp\GreenThread\async;
use Amp\Loop;
use PHPUnit\Framework\TestCase;

class InMemoryStreamTest extends TestCase {
    public function testSingleReadConsumesEverything() {
        async(function () {
            $stream = new InMemoryStream("foobar");
            $this->assertSame("foobar", $stream->read());
            $this->assertNull($stream->read());
        });

        Loop::run();
    }
}
