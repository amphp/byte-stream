<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\OutputBuffer;
use Amp\PHPUnit\AsyncTestCase;

class OutputBufferTest extends AsyncTestCase
{
    public function testWrite(): void
    {
        $output = new OutputBuffer();
        $output->write('foo')->join();
        $output->end()->join();

        self::assertSame('foo', $output->buffer());
    }

    public function testEnd(): void
    {
        $output = new OutputBuffer();
        $output->write('foo')->join();
        $output->end('bar')->join();

        self::assertSame('foobar', $output->buffer());
    }

    public function testThrowsOnWritingToClosedBuffer(): void
    {
        $this->expectException(ClosedException::class);

        $output = new OutputBuffer();
        $output->end('foo')->join();
        $output->write('bar')->join();
    }

    public function testThrowsOnEndingToClosedBuffer(): void
    {
        $this->expectException(ClosedException::class);

        $output = new OutputBuffer();
        $output->end('foo')->join();
        $output->end('bar')->join();
    }
}
