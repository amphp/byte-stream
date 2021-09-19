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
        $output->write('foo')->await();
        $output->end()->await();

        self::assertSame('foo', $output->buffer());
    }

    public function testEnd(): void
    {
        $output = new OutputBuffer();
        $output->write('foo')->await();
        $output->end('bar')->await();

        self::assertSame('foobar', $output->buffer());
    }

    public function testThrowsOnWritingToClosedBuffer(): void
    {
        $this->expectException(ClosedException::class);

        $output = new OutputBuffer();
        $output->end('foo')->await();
        $output->write('bar')->await();
    }

    public function testThrowsOnEndingToClosedBuffer(): void
    {
        $this->expectException(ClosedException::class);

        $output = new OutputBuffer();
        $output->end('foo')->await();
        $output->end('bar')->await();
    }
}
