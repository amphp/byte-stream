<?php declare(strict_types=1);

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;

final class WritableBufferTest extends AsyncTestCase
{
    public function testWrite(): void
    {
        $output = new WritableBuffer();
        $output->write('foo');
        $output->end();

        self::assertSame('foo', $output->buffer());
    }

    public function testEnd(): void
    {
        $output = new WritableBuffer();
        $output->write('foo');
        $output->write('bar');
        $output->end();

        self::assertSame('foobar', $output->buffer());
    }

    public function testDoubleClose(): void
    {
        $output = new WritableBuffer();
        $output->close();
        $output->close();

        $this->expectNotToPerformAssertions();
    }

    public function testThrowsOnWritingToClosedBuffer(): void
    {
        $this->expectException(ClosedException::class);

        $output = new WritableBuffer();
        $output->write('foo');
        $output->end();
        $output->write('bar');
    }

    public function testThrowsOnEndingToClosedBuffer(): void
    {
        $this->expectException(ClosedException::class);

        $output = new WritableBuffer();
        $output->write('foo');
        $output->end();
        $output->end();
    }
}
