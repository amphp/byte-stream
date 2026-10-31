<?php declare(strict_types=1);

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use function Amp\delay;

final class WritableIterableStreamTest extends AsyncTestCase
{
    public function testOnCloseInvokedOnClose(): void
    {
        $stream = new WritableIterableStream(0);

        $invoked = false;
        $stream->onClose(function () use (&$invoked): void {
            $invoked = true;
        });

        self::assertFalse($invoked);

        $stream->close();

        delay(0); // Pass control to event-loop to invoke callbacks.

        self::assertTrue($invoked);
        self::assertTrue($stream->isClosed());
    }

    public function testOnCloseInvokedAfterClose(): void
    {
        $stream = new WritableIterableStream(0);
        $stream->close();

        $stream->onClose($this->createCallback(1));

        delay(0); // Pass control to event-loop to invoke callbacks.
    }
}
