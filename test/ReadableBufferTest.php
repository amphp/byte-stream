<?php declare(strict_types=1);

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use function Amp\delay;

final class ReadableBufferTest extends AsyncTestCase
{
    public function testSingleReadConsumesEverything(): void
    {
        $stream = new ReadableBuffer("foobar");
        self::assertSame("foobar", $stream->read());
        self::assertNull($stream->read());
    }

    public function testEmpty(): void
    {
        $stream = new ReadableBuffer("");
        self::assertNull($stream->read());
    }

    public function testOnClose(): void
    {
        $stream = new ReadableBuffer("foobar");

        $stream->onClose($this->createCallback(1));

        $invoked = false;
        $stream->onClose(function () use (&$invoked): void {
            $invoked = true;
        });

        $stream->read();

        delay(0); // Pass control to event-loop to invoke callbacks.

        self::assertTrue($invoked);

        $stream->onClose($this->createCallback(1));

        delay(0); // Pass control to event-loop to invoke callbacks.
    }
}
