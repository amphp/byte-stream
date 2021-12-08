<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;

final class WriteBufferTest extends AsyncTestCase
{
    public function testWrite(): void
    {
        $output = new WriteBuffer();
        $output->write('foo');
        $output->end();

        self::assertSame('foo', $output->buffer());
    }

    public function testEnd(): void
    {
        $output = new WriteBuffer();
        $output->write('foo');
        $output->end('bar');

        self::assertSame('foobar', $output->buffer());
    }

    public function testThrowsOnWritingToClosedBuffer(): void
    {
        $this->expectException(ClosedException::class);

        $output = new WriteBuffer();
        $output->end('foo');
        $output->write('bar');
    }

    public function testThrowsOnEndingToClosedBuffer(): void
    {
        $this->expectException(ClosedException::class);

        $output = new WriteBuffer();
        $output->end('foo');
        $output->end('bar');
    }
}
