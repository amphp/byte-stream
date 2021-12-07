<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;

final class WriteBufferTest extends AsyncTestCase
{
    public function testWrite(): void
    {
        $output = new WriteBuffer();
        $output->write('foo')->await();
        $output->end()->await();

        self::assertSame('foo', $output->buffer());
    }

    public function testEnd(): void
    {
        $output = new WriteBuffer();
        $output->write('foo')->await();
        $output->end('bar')->await();

        self::assertSame('foobar', $output->buffer());
    }

    public function testThrowsOnWritingToClosedBuffer(): void
    {
        $this->expectException(ClosedException::class);

        $output = new WriteBuffer();
        $output->end('foo')->await();
        $output->write('bar')->await();
    }

    public function testThrowsOnEndingToClosedBuffer(): void
    {
        $this->expectException(ClosedException::class);

        $output = new WriteBuffer();
        $output->end('foo')->await();
        $output->end('bar')->await();
    }
}
