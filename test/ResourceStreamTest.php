<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\PendingReadError;
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\ByteStream\StreamException;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\Future\spawn;
use function Revolt\EventLoop\defer;

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

        $message = \str_repeat(".", self::LARGE_MESSAGE_SIZE);

        defer(fn () => $a->end($message));

        $received = "";
        while (null !== $chunk = $b->read()) {
            $received .= $chunk;
        }

        self::assertSame($message, $received);
    }

    public function testManySmallPayloads(): void
    {
        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat(".", 8192 /* default chunk size */);

        defer(function () use (&$i, $a, $message): void {
            for ($i = 0; $i < 128; $i++) {
                $a->write($message);
            }
            $a->end();
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

        $this->setTimeout(5000);

        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat(".", self::LARGE_MESSAGE_SIZE);

        $writeFuture = spawn(fn () => $a->write($message));

        $b->read();
        $b->close();

        $writeFuture->join();
    }

    public function testThrowsOnExternallyShutdownStreamWithSmallPayloads(): void
    {
        $this->expectException(StreamException::class);

        $this->setTimeout(5000);

        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat(".", 8192 /* default chunk size */);

        for ($i = 0; $i < 128; $i++) {
            $writeFuture = spawn(fn () => $a->write($message));
        }

        $b->read();
        $b->close();

        $writeFuture->join();
    }

    public function testThrowsOnCloseBeforeWritingComplete(): void
    {
        $this->expectException(ClosedException::class);

        /** @noinspection PhpUnusedLocalVariableInspection Required to keep reference */
        [$a, $b] = $this->getStreamPair(4096);

        $message = \str_repeat(".", 8192 /* default chunk size */);

        $writeFuture = spawn(fn () => $a->write($message));

        $a->close();

        $writeFuture->join();
    }

    public function testThrowsOnStreamNotWritable(): void
    {
        $this->expectException(StreamException::class);

        [$a] = $this->getStreamPair();

        $message = \str_repeat(".", 8192 /* default chunk size */);

        $a->close();

        $a->write($message);
    }

    public function testThrowsOnReferencingClosedStream(): void
    {
        $this->expectException(\Error::class);

        [, $b] = $this->getStreamPair();

        $b->close();

        $b->reference();
    }

    public function testThrowsOnUnreferencingClosedStream(): void
    {
        $this->expectException(\Error::class);

        [, $b] = $this->getStreamPair();

        $b->close();

        $b->unreference();
    }

    public function testThrowsOnPendingRead(): void
    {
        $this->expectException(PendingReadError::class);

        /** @noinspection PhpUnusedLocalVariableInspection Required to keep reference */
        [$a, $b] = $this->getStreamPair();

//        try {
            spawn(fn () => $b->read()); // Will not resolve.
            spawn(fn () => $b->read())->join();
//        } finally {
//            $b->close();
//        }
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

        $message = \str_repeat(".", 8192 /* default chunk size */);

        $a->end($message);

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

        $a->end($message);

        $received = "";
        while (null !== $chunk = $b->read()) {
            $received .= $chunk;
        }

        self::assertSame($message, $received);
    }

    public function testCloseStreamAfterEndPayload(): void
    {
        [$a, $b] = $this->getStreamPair();

        $message = \str_repeat(".", 8192 /* default chunk size */);

        $a->end($message);

        $received = "";
        while (null !== $chunk = $b->read()) {
            $received .= $chunk;
        }

        self::assertSame($message, $received);
    }

//    public function testIssue47()
//    {
//        $middle = \tempnam(\sys_get_temp_dir(), 'byte-stream-middle-');
//
//        \Amp\Promise\rethrow(\Amp\ByteStream\pipe(
//            new ResourceInputStream(\fopen(__FILE__, 'rb')),
//            new ResourceOutputStream(\fopen($middle, 'wb'))
//        ));
//
//        $middleReadStream = new ResourceInputStream(\fopen($middle, 'rb'));
//        $buffer = '';
//
//        while (\strlen($buffer) < \filesize(__FILE__)) {
//            $buffer .= $middleReadStream->read();
//        }
//
//        $this->assertStringEqualsFile(__FILE__, $buffer);
//    }

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
}
