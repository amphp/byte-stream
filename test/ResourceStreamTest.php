<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\PendingReadError;
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\ByteStream\StreamException;
use Amp\Loop;
use Amp\Success;
use PHPUnit\Framework\TestCase;

class ResourceStreamTest extends TestCase
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
        Loop::run(function () {
            list($a, $b) = $this->getStreamPair();

            $message = \str_repeat(".", self::LARGE_MESSAGE_SIZE);

            \Amp\Promise\rethrow($a->end($message));

            $received = "";
            while (null !== $chunk = yield $b->read()) {
                $received .= $chunk;
            }

            $this->assertSame($message, $received);
        });
    }

    public function testManySmallPayloads()
    {
        Loop::run(function () {
            list($a, $b) = $this->getStreamPair();

            $message = \str_repeat(".", 8192 /* default chunk size */);

            for ($i = 0; $i < 128; $i++) {
                \Amp\Promise\rethrow($a->write($message));
            }
            $a->end();

            $received = "";
            while (null !== $chunk = yield $b->read()) {
                $received .= $chunk;
            }

            $this->assertSame(\str_repeat($message, $i), $received);
        });
    }

    public function testThrowsOnExternallyShutdownStreamWithLargePayload()
    {
        $this->expectException(StreamException::class);

        Loop::run(function () {
            try { /* prevent crashes with phpdbg due to SIGPIPE not being handled... */
                Loop::onSignal(\defined("SIGPIPE") ? SIGPIPE : 13, function () {
                });
            } catch (Loop\UnsupportedFeatureException $e) {
            }

            list($a, $b) = $this->getStreamPair();

            $message = \str_repeat(".", self::LARGE_MESSAGE_SIZE);

            $writePromise = $a->write($message);

            yield $b->read();
            $b->close();

            yield $writePromise;
        });
    }

    public function testThrowsOnExternallyShutdownStreamWithSmallPayloads()
    {
        $this->expectException(StreamException::class);

        Loop::run(function () {
            try { /* prevent crashes with phpdbg due to SIGPIPE not being handled... */
                Loop::onSignal(\defined("SIGPIPE") ? SIGPIPE : 13, function () {
                });
            } catch (Loop\UnsupportedFeatureException $e) {
            }

            list($a, $b) = $this->getStreamPair();

            $message = \str_repeat(".", 8192 /* default chunk size */);

            for ($i = 0; $i < 128; $i++) {
                $lastWritePromise = $a->write($message);
            }

            yield $b->read();
            $b->close();

            yield $lastWritePromise;
        });
    }

    public function testThrowsOnCloseBeforeWritingComplete()
    {
        $this->expectException(ClosedException::class);

        Loop::run(function () {
            list($a, $b) = $this->getStreamPair(4096);

            $message = \str_repeat(".", 8192 /* default chunk size */);

            $lastWritePromise = $a->end($message);

            $a->close();

            yield $lastWritePromise;
        });
    }

    public function testThrowsOnStreamNotWritable()
    {
        $this->expectException(StreamException::class);

        Loop::run(function () {
            list($a, $b) = $this->getStreamPair();

            $message = \str_repeat(".", 8192 /* default chunk size */);

            $a->close();

            $lastWritePromise = $a->end($message);

            yield $lastWritePromise;
        });
    }

    public function testThrowsOnReferencingClosedStream()
    {
        $this->expectException(\Error::class);

        Loop::run(function () {
            list($a, $b) = $this->getStreamPair();

            $b->close();

            $b->reference();
        });
    }

    public function testThrowsOnUnreferencingClosedStream()
    {
        $this->expectException(\Error::class);

        Loop::run(function () {
            list($a, $b) = $this->getStreamPair();

            $b->close();

            $b->unreference();
        });
    }

    public function testThrowsOnPendingRead()
    {
        $this->expectException(PendingReadError::class);

        Loop::run(function () {
            list($a, $b) = $this->getStreamPair();

            $b->read();
            $b->read();
        });
    }

    public function testResolveSuccessOnClosedStream()
    {
        Loop::run(function () {
            list($a, $b) = $this->getStreamPair();

            $b->close();

            $this->assertInstanceOf(Success::class, $b->read());
        });
    }

    public function testChunkedPayload()
    {
        Loop::run(function () {
            list($a, $b) = $this->getStreamPair(4096);

            $message = \str_repeat(".", 8192 /* default chunk size */);

            \Amp\Promise\rethrow($a->end($message));

            $received = "";
            while (null !== $chunk = yield $b->read()) {
                $received .= $chunk;
            }

            $this->assertSame($message, $received);
        });
    }

    public function testEmptyPayload()
    {
        Loop::run(function () {
            list($a, $b) = $this->getStreamPair(4096);

            $message = "";

            \Amp\Promise\rethrow($a->end($message));

            $received = "";
            while (null !== $chunk = yield $b->read()) {
                $received .= $chunk;
            }

            $this->assertSame($message, $received);
        });
    }

    public function testCloseStreamAfterEndPayload()
    {
        Loop::run(function () {
            list($a, $b) = $this->getStreamPair();

            $message = \str_repeat(".", 8192 /* default chunk size */);

            \Amp\Promise\rethrow($a->end($message));

            $received = "";
            while (null !== $chunk = yield $b->read()) {
                $received .= $chunk;
            }

            $this->assertSame($message, $received);
        });
    }

    public function testIssue47()
    {
        Loop::run(function () {
            $middle = \tempnam(\sys_get_temp_dir(), 'byte-stream-middle-');

            \Amp\ByteStream\pipe(
                new ResourceInputStream(fopen(__FILE__, 'rb')),
                new ResourceOutputStream(fopen($middle, 'wb'))
            );

            $middleReadStream = new ResourceInputStream(fopen($middle, 'rb'));

            while (null !== $chunk = yield $middleReadStream->read()) {
                unset($chunk);
            }

            $this->assertTrue(true);
        });
    }
}
