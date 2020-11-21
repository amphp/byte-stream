<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\PendingReadError;
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\ByteStream\StreamException;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\async;
use function Amp\await;
use function Amp\defer;

class ResourceStreamTest extends AsyncTestCase
{
    const LARGE_MESSAGE_SIZE = 1 << 20; // 1 MB

    public function getStreamPair($outputChunkSize = null, $inputChunkSize = ResourceInputStream::DEFAULT_CHUNK_SIZE)
    {
        $domain = \stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX;
        list($left, $right) = @\stream_socket_pair($domain, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

        $a = new ResourceOutputStream($left, $outputChunkSize);
        $b = new ResourceInputStream($right, $inputChunkSize);

        return [$a, $b];
    }

    public function testLargePayloads()
    {
        list($a, $b) = $this->getStreamPair();

        $message = \str_repeat(".", self::LARGE_MESSAGE_SIZE);

        defer(fn() => $a->end($message));

        $received = "";
        while (null !== $chunk = $b->read()) {
            $received .= $chunk;
        }

        $this->assertSame($message, $received);
    }

    public function testManySmallPayloads()
    {
        list($a, $b) = $this->getStreamPair();

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

        $this->assertSame(\str_repeat($message, $i), $received);
    }

    public function testThrowsOnExternallyShutdownStreamWithLargePayload()
    {
        $this->expectException(StreamException::class);

        $this->setTimeout(5000);

        list($a, $b) = $this->getStreamPair();

        $message = \str_repeat(".", self::LARGE_MESSAGE_SIZE);

        $writePromise = async(fn() => $a->write($message));

        $b->read();
        $b->close();

        await($writePromise);
    }

    public function testThrowsOnExternallyShutdownStreamWithSmallPayloads()
    {
        $this->expectException(StreamException::class);

        $this->setTimeout(5000);

        list($a, $b) = $this->getStreamPair();

        $message = \str_repeat(".", 8192 /* default chunk size */);

        for ($i = 0; $i < 128; $i++) {
            $writePromise = async(fn() => $a->write($message));
        }

        $b->read();
        $b->close();

        await($writePromise);
    }

    public function testThrowsOnCloseBeforeWritingComplete()
    {
        $this->expectException(ClosedException::class);

        /** @noinspection PhpUnusedLocalVariableInspection Required to keep reference */
        list($a, $b) = $this->getStreamPair(4096);

        $message = \str_repeat(".", 8192 /* default chunk size */);

        $writePromise = async(fn() => $a->write($message));

        $a->close();

        await($writePromise);
    }

    public function testThrowsOnStreamNotWritable()
    {
        $this->expectException(StreamException::class);

        list($a) = $this->getStreamPair();

        $message = \str_repeat(".", 8192 /* default chunk size */);

        $a->close();

        $a->write($message);
    }

    public function testThrowsOnReferencingClosedStream()
    {
        $this->expectException(\Error::class);

        list(, $b) = $this->getStreamPair();

        $b->close();

        $b->reference();
    }

    public function testThrowsOnUnreferencingClosedStream()
    {
        $this->expectException(\Error::class);

        list(, $b) = $this->getStreamPair();

        $b->close();

        $b->unreference();
    }

    public function testThrowsOnPendingRead()
    {
        $this->expectException(PendingReadError::class);

        /** @noinspection PhpUnusedLocalVariableInspection Required to keep reference */
        list($a, $b) = $this->getStreamPair();

        async(fn() => $b->read()); // Will not resolve.
        await(async(fn () => $b->read()));
    }

    public function testResolveSuccessOnClosedStream()
    {
        list(, $b) = $this->getStreamPair();

        $b->close();

        $this->assertNull($b->read());
    }

    public function testChunkedPayload()
    {
        list($a, $b) = $this->getStreamPair(4096);

        $message = \str_repeat(".", 8192 /* default chunk size */);

        $a->end($message);

        $received = "";
        while (null !== $chunk = $b->read()) {
            $received .= $chunk;
        }

        $this->assertSame($message, $received);
    }

    public function testEmptyPayload()
    {
        list($a, $b) = $this->getStreamPair(4096);

        $message = "";

        $a->end($message);

        $received = "";
        while (null !== $chunk = $b->read()) {
            $received .= $chunk;
        }

        $this->assertSame($message, $received);
    }

    public function testCloseStreamAfterEndPayload()
    {
        list($a, $b) = $this->getStreamPair();

        $message = \str_repeat(".", 8192 /* default chunk size */);

        $a->end($message);

        $received = "";
        while (null !== $chunk = $b->read()) {
            $received .= $chunk;
        }

        $this->assertSame($message, $received);
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

    public function testSetChunkSize()
    {
        list($a, $b) = $this->getStreamPair();
        $a->setChunkSize(1);
        $b->setChunkSize(1);

        $a->write('foo');

        $this->assertSame('f', $b->read());

        $b->setChunkSize(3);
        $this->assertSame('oo', $b->read());
    }
}
