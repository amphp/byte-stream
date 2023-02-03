<?php declare(strict_types=1);

namespace Amp\ByteStream;

use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\Future;
use Amp\PHPUnit\AsyncTestCase;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\delay;

final class ResourceStreamTest extends AsyncTestCase
{
    public const LARGE_MESSAGE_SIZE = 1 << 20; // 1 MB

    /**
     * @return array{WritableResourceStream, ReadableResourceStream}
     */
    public function getStreamPair(
        ?int $outputChunkSize = null,
        int $inputChunkSize = ReadableResourceStream::DEFAULT_CHUNK_SIZE
    ): array {
        $domain = \PHP_OS_FAMILY === 'Windows' ? STREAM_PF_INET : STREAM_PF_UNIX;
        [$left, $right] = @\stream_socket_pair($domain, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

        $a = new WritableResourceStream($left, $outputChunkSize);
        $b = new ReadableResourceStream($right, $inputChunkSize);

        return [$a, $b];
    }

    public function testLargePayloads(): void
    {
        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat("*", self::LARGE_MESSAGE_SIZE);

        $future = async(function () use ($a, $message) {
            $a->write($message);
            $a->end();
        });

        self::assertSame($message, buffer($b));

        $future->await();
    }

    public function testManySmallPayloads(): void
    {
        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat("*", 8192 /* default chunk size */);

        EventLoop::queue(function () use (&$i, $a, $message): void {
            for ($i = 0; $i < 128; $i++) {
                $a->write($message);
            }
            $a->end();
        });

        $buffer = buffer($b);
        self::assertSame(\str_repeat($message, $i), $buffer);
    }

    public function testThrowsOnExternallyShutdownStreamWithLargePayload(): void
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            // See https://stackoverflow.com/a/28799536/2373138
            $this->markTestSkipped('Send buffer can grow large on Windows, so this test doesn\'t work.');
        }

        $this->setTimeout(5);

        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat("*", self::LARGE_MESSAGE_SIZE);

        $writeFuture = async(fn () => $a->write($message));

        $b->close();

        $this->expectException(StreamException::class);

        $writeFuture->await();
    }

    public function testThrowsOnExternallyShutdownStreamWithSmallPayloads(): void
    {
        $this->setTimeout(5);

        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat("*", 8192 /* default chunk size */);

        $writeFuture = Future::complete();

        for ($i = 0; $i < 128; $i++) {
            $writeFuture?->ignore();
            $writeFuture = async(fn () => $a->write($message));
        }

        $b->read();
        $b->close();

        $this->expectException(StreamException::class);

        $writeFuture->await();
    }

    public function testThrowsOnCloseBeforeWritingComplete(): void
    {
        /** @noinspection PhpUnusedLocalVariableInspection Required to keep reference */
        [$a, $b] = $this->getStreamPair(4096);

        $message = \str_repeat("*", 8192 /* default chunk size */);

        $writeFuture = async(fn () => $a->write($message));

        $a->close();

        $this->expectException(ClosedException::class);

        $writeFuture->await();
    }

    public function testThrowsOnStreamNotWritable(): void
    {
        [$a] = $this->getStreamPair();

        $message = \str_repeat("*", 8192 /* default chunk size */);

        $a->close();

        $this->expectException(StreamException::class);

        $a->write($message);
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

    public function testCloseWithPendingRead(): void
    {
        /** @noinspection PhpUnusedLocalVariableInspection Required to keep reference */
        [$a, $b] = $this->getStreamPair();

        $future = async($b->read(...));
        async($b->close(...));

        self::assertFalse($b->isClosed());

        self::assertNull($future->await());

        self::assertTrue($b->isClosed());
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

        $a->write($message);
        $a->end();

        self::assertSame($message, buffer($b));
    }

    public function testEmptyPayload(): void
    {
        [$a, $b] = $this->getStreamPair(4096);

        $message = "";

        $a->write($message);
        $a->end();

        self::assertSame($message, buffer($b));
    }

    public function testCloseStreamAfterEndPayload(): void
    {
        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat("*", 8192 /* default chunk size */);

        $future = async(function () use ($a, $message) {
            $a->write($message);
            $a->end();
        });

        self::assertSame($message, buffer($b));

        $future->await();
    }

    public function testIssue47()
    {
        $this->markTestSkipped('Not currently supported');

        $middle = \tempnam(\sys_get_temp_dir(), 'byte-stream-middle-');

        EventLoop::queue(function () use ($middle): void {
            pipe(
                new ReadableBuffer(\file_get_contents(__FILE__)),
                $destination = new WritableResourceStream(\fopen($middle, 'wb'))
            );

            $destination->close();
        });

        $middleReadStream = new ReadableResourceStream(\fopen($middle, 'rb'));
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

        $a->write('foo');

        self::assertSame('f', $b->read());

        $b->setChunkSize(3);
        self::assertSame('oo', $b->read());
    }

    public function testReadLength(): void
    {
        [$a, $b] = $this->getStreamPair();
        $a->write('foobar');

        self::assertSame('f', $b->read(limit: 1));
        self::assertSame('oo', $b->read(limit: 2));
        self::assertSame('bar', $b->read(limit: 100));
    }

    public function testCancellationBeforeRead(): void
    {
        [$a, $b] = $this->getStreamPair();

        $deferredCancellation = new DeferredCancellation();

        $future = async(fn () => $b->read($deferredCancellation->getCancellation()));

        $deferredCancellation->cancel();

        async(fn () => $a->write('foo'))->ignore();

        $this->expectException(CancelledException::class);
        $future->await();
    }

    public function testCancellationAfterRead(): void
    {
        [$a, $b] = $this->getStreamPair();

        $deferredCancellation = new DeferredCancellation();

        $future = async(fn () => $b->read($deferredCancellation->getCancellation()));

        $a->write('foo');

        delay(0.001); // Tick event loop to invoke read watcher.

        $deferredCancellation->cancel();

        self::assertSame('foo', $future->await());
    }
}
