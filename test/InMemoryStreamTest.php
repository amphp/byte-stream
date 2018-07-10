<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Concurrent\Task;
use PHPUnit\Framework\TestCase;

class InMemoryStreamTest extends TestCase
{
    public function testSingleReadConsumesEverything(): void
    {
        Task::await(Task::async(function () {
            $stream = new InMemoryStream("foobar");
            $this->assertSame("foobar", $stream->read());
            $this->assertNull($stream->read());
        }));
    }
}
