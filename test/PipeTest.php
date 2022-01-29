<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use function Amp\async;
use function Amp\delay;
use function Amp\now;

final class PipeTest extends AsyncTestCase
{
    public function testPipeFunction(): void
    {
        $stream = new IterableStream(["abc", "def"]);
        $buffer = new WritableBuffer;

        self::assertSame(6, pipe($stream, $buffer));

        $buffer->end();
        self::assertSame("abcdef", $buffer->buffer());
    }

    public function testPipeBufferSizeTooLarge(): void
    {
        $pipe = new Pipe(5);

        async(function () use ($pipe, &$writeStart, &$writeEnd) {
            $writeStart = now();
            $pipe->getSink()->write('foobar');
            $writeEnd = now();
            $pipe->getSink()->end();
        });

        delay(0.5);

        $readStart = now();
        self::assertSame('foobar', $pipe->getSource()->read());
        self::assertNull($pipe->getSource()->read());
        $readEnd = now();

        // Write blocks, because it is too large
        self::assertGreaterThanOrEqual(0.5, $writeEnd - $writeStart);
        self::assertLessThan(0.1, $readEnd - $readStart);
    }

    public function testPipeBufferSizeFits(): void
    {
        $pipe = new Pipe(6);

        async(function () use ($pipe, &$writeStart, &$writeEnd) {
            $writeStart = now();
            $pipe->getSink()->write('foobar');
            $writeEnd = now();
            $pipe->getSink()->end();
        });

        delay(0.5);

        $readStart = now();
        self::assertSame('foobar', $pipe->getSource()->read());
        self::assertNull($pipe->getSource()->read());
        $readEnd = now();

        // Write doesn't block, because it fits into the buffer
        self::assertLessThan(0.1, $writeEnd - $writeStart);
        self::assertLessThan(0.1, $readEnd - $readStart);
    }
}
