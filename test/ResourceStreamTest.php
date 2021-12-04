<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\PendingReadError;
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\ByteStream\StreamException;
use Amp\DeferredCancellation;
use Amp\CancelledException;
use Amp\Future;
use Amp\PHPUnit\AsyncTestCase;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\ByteStream\pipe;
use function Amp\delay;

class ResourceStreamTest extends AsyncTestCase
{
    const LARGE_MESSAGE_SIZE = 1 << 20; // 1 MB

    public function getStreamPair(
        $outputChunkSize = null,
        $inputChunkSize = ResourceInputStream::DEFAULT_CHUNK_SIZE
    ): array {
        $domain = \stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX;
        [$left, $right] = @\stream_socket_pair($domain, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

        $a = new ResourceOutputStream($left, $outputChunkSize);
        $b = new ResourceInputStream($right, $inputChunkSize);

        return [$a, $b];
    }

    public function testLargePayloads(): void
    {
        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat("*", self::LARGE_MESSAGE_SIZE);

        $a->end($message)->ignore();

        $received = "";
        while (null !== $chunk = $b->read()) {
            $received .= $chunk;
        }

        self::assertSame($message, $received);
    }

    public function testManySmallPayloads(): void
    {
        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat("*", 8192 /* default chunk size */);

        EventLoop::queue(function () use (&$i, $a, $message): void {
            for ($i = 0; $i < 128; $i++) {
                $a->write($message)->await();
            }
            $a->end()->await();
        });

        $received = "";
        while (null !== $chunk = $b->read()) {
            $received .= $chunk;
        }

        self::assertSame(\str_repeat($message, $i), $received);
    }

    public function testThrowsOnExternallyShutdownStreamWithLargePayload(): void
    {
        $this->expectException(StreamException::class);

        $this->setTimeout(5);

        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat("*", self::LARGE_MESSAGE_SIZE);

        $writeFuture = $a->write($message);

        $b->read();
        $b->close();

        $writeFuture->await();
    }

    public function testThrowsOnExternallyShutdownStreamWithSmallPayloads(): void
    {
        $this->expectException(StreamException::class);

        $this->setTimeout(5);

        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat("*", 8192 /* default chunk size */);

        $writeFuture = Future::complete(null);

        for ($i = 0; $i < 128; $i++) {
            $writeFuture?->ignore();
            $writeFuture = $a->write($message);
        }

        $b->read();
        $b->close();

        $writeFuture->await();
    }

    public function testThrowsOnCloseBeforeWritingComplete(): void
    {
        $this->expectException(ClosedException::class);

        /** @noinspection PhpUnusedLocalVariableInspection Required to keep reference */
        [$a, $b] = $this->getStreamPair(4096);

        $message = \str_repeat("*", 8192 /* default chunk size */);

        $writeFuture = $a->write($message);

        $a->close();

        $writeFuture->await();
    }

    public function testThrowsOnStreamNotWritable(): void
    {
        $this->expectException(StreamException::class);

        [$a] = $this->getStreamPair();

        $message = \str_repeat("*", 8192 /* default chunk size */);

        $a->close();

        $a->write($message)->await();
    }

    public function testReferencingClosedStream(): void
    {
        [, $b] = $this->getStreamPair();

        $b->close();

        $b->reference();

        self::assertNull($b->read());
    }

    public function testUnreferencingClosedStream(): void
    {
        [, $b] = $this->getStreamPair();

        $b->close();

        $b->unreference();

        self::assertNull($b->read());
    }

    public function testThrowsOnPendingRead(): void
    {
        $this->expectException(PendingReadError::class);

        /** @noinspection PhpUnusedLocalVariableInspection Required to keep reference */
        [$a, $b] = $this->getStreamPair();

        async(fn () => $b->read())->ignore(); // Will not resolve.
        async(fn () => $b->read())->await();
    }

    public function testResolveSuccessOnClosedStream(): void
    {
        [, $b] = $this->getStreamPair();

        $b->close();

        self::assertNull($b->read());
    }

    public function testChunkedPayload(): void
    {
        [$a, $b] = $this->getStreamPair(4096);

        $message = \str_repeat("*", 8192 /* default chunk size */);

        $a->end($message)->ignore();

        $received = "";
        while (null !== $chunk = $b->read()) {
            $received .= $chunk;
        }

        self::assertSame($message, $received);
    }

    public function testEmptyPayload(): void
    {
        [$a, $b] = $this->getStreamPair(4096);

        $message = "";

        $a->end($message)->ignore();

        $received = "";
        while (null !== $chunk = $b->read()) {
            $received .= $chunk;
        }

        self::assertSame($message, $received);
    }

    public function testCloseStreamAfterEndPayload(): void
    {
        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat("*", 8192 /* default chunk size */);

        $a->end($message)->ignore();

        $received = "";
        while (null !== $chunk = $b->read()) {
            $received .= $chunk;
        }

        self::assertSame($message, $received);
    }

    public function testIssue47()
    {
        $middle = \tempnam(\sys_get_temp_dir(), 'byte-stream-middle-');

        EventLoop::queue(function () use ($middle): void {
            pipe(
                new InMemoryStream(\file_get_contents(__FILE__)),
                $destination = new ResourceOutputStream(\fopen($middle, 'wb'))
            );

            $destination->close();
        });

        $middleReadStream = new ResourceInputStream(\fopen($middle, 'rb'));
        $buffer = '';

        while (\strlen($buffer) < \filesize(__FILE__)) {
            $buffer .= $middleReadStream->read();
        }

        $this->assertStringEqualsFile(__FILE__, $buffer);
    }

    public function testSetChunkSize(): void
    {
        [$a, $b] = $this->getStreamPair();
        $a->setChunkSize(1);
        $b->setChunkSize(1);

        $a->write('foo')->await();

        self::assertSame('f', $b->read());

        $b->setChunkSize(3);
        self::assertSame('oo', $b->read());
    }

    public function testCancellationBeforeRead(): void
    {
        [$a, $b] = $this->getStreamPair();

        $cancellationSource = new DeferredCancellation();

        $future = async(fn() => $b->read($cancellationSource->getCancellation()));

        $cancellationSource->cancel();

        $a->write('foo')->await();

        $this->expectException(CancelledException::class);
        $future->await();
    }

    public function testCancellationAfterRead(): void
    {
        [$a, $b] = $this->getStreamPair();

        $cancellationSource = new DeferredCancellation();

        $future = async(fn() => $b->read($cancellationSource->getCancellation()));

        $a->write('foo')->await();

        delay(0); // Tick event loop to invoke read watcher.

        $cancellationSource->cancel();

        self::assertSame('foo', $future->await());
    }
}
