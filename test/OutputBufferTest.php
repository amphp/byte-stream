<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\OutputBuffer;
use Amp\Loop;
use Amp\PHPUnit\TestCase;

class OutputBufferTest extends TestCase {
    public function testWrite() {
        Loop::run(function () {
            $output = new OutputBuffer();
            $output->write('foo');
            $output->end();

            $memory = new InMemoryStream(yield $output);

            $this->assertSame('foo', yield $memory->read());
        });
    }

    public function testEnd() {
        Loop::run(function () {
            $output = new OutputBuffer();
            $output->write('foo');
            $output->end('bar');

            $memory = new InMemoryStream(yield $output);

            $this->assertSame('foobar', yield $memory->read());
        });
    }

    public function testThrowsOnWritingToClosedBuffer() {
        $this->expectException(ClosedException::class);

        Loop::run(function () {
            $output = new OutputBuffer();
            $output->end('foo');
            $output->write('bar');
        });
    }

    public function testThrowsOnEndingToClosedBuffer() {
        $this->expectException(ClosedException::class);

        Loop::run(function () {
            $output = new OutputBuffer();
            $output->end('foo');
            $output->end('bar');
        });
    }
}
