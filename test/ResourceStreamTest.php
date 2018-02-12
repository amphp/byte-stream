<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\PendingReadError;
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\ByteStream\StreamException;
use function Amp\GreenThread\async;
use Amp\Loop;
use function Amp\Promise\rethrow;
use function Amp\Promise\wait;
use Amp\Success;
use PHPUnit\Framework\TestCase;

class ResourceStreamTest extends TestCase {
    const LARGE_MESSAGE_SIZE = 1 << 20; // 1 MB

    public function getStreamPair($outputChunkSize = null, $inputChunkSize = 8192) {
        $domain = \stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX;
        list($left, $right) = @\stream_socket_pair($domain, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

        $a = new ResourceOutputStream($left, $outputChunkSize);
        $b = new ResourceInputStream($right, $inputChunkSize);

        return [$a, $b];
    }

    public function testLargePayloads() {
        wait(async(function () {
            list($a, $b) = $this->getStreamPair();

            $message = \str_repeat(".", self::LARGE_MESSAGE_SIZE);
            rethrow(async(function () use ($a) {
                $a->end($message);
            }));

            $received = "";
            while (null !== $chunk = $b->read()) {
                $received .= $chunk;
            }

            $this->assertSame($message, $received);
        }));
    }

    public function testManySmallPayloads() {
        wait(async(function () {
            list($a, $b) = $this->getStreamPair();

            $message = \str_repeat(".", 8192 /* default chunk size */);

            for ($i = 0; $i < 128; $i++) {
                \Amp\Promise\rethrow($a->write($message));
            }
            $a->end();

            $received = "";
            while (null !== $chunk = $b->read()) {
                $received .= $chunk;
            }

            $this->assertSame(\str_repeat($message, $i), $received);
        }));
    }

    public function testThrowsOnExternallyShutdownStreamWithLargePayload() {
        $this->expectException(StreamException::class);

        wait(async(function () {
            try { /* prevent crashes with phpdbg due to SIGPIPE not being handled... */
                Loop::onSignal(defined("SIGPIPE") ? SIGPIPE : 13, function () {});
            } catch (Loop\UnsupportedFeatureException $e) {
            }

            list($a, $b) = $this->getStreamPair();

            $message = \str_repeat(".", self::LARGE_MESSAGE_SIZE);

            $writePromise = async([$a, 'write'], $message);

            $b->read();
            $b->close();

            await($writePromise);
        }));
    }

    public function testThrowsOnExternallyShutdownStreamWithSmallPayloads() {
        $this->expectException(StreamException::class);

        wait(async(function () {
            try { /* prevent crashes with phpdbg due to SIGPIPE not being handled... */
                Loop::onSignal(defined("SIGPIPE") ? SIGPIPE : 13, function () {});
            } catch (Loop\UnsupportedFeatureException $e) {
            }

            list($a, $b) = $this->getStreamPair();

            $message = \str_repeat(".", 8192 /* default chunk size */);

            for ($i = 0; $i < 128; $i++) {
                $lastWritePromise = async([$a, 'write'], $message);
            }

            $b->read();
            $b->close();

            await($lastWritePromise);
        }));
    }

    public function testThrowsOnCloseBeforeWritingComplete() {
        $this->expectException(ClosedException::class);

        wait(async(function () {
            list($a, $b) = $this->getStreamPair(4096);

            $message = \str_repeat(".", 8192 /* default chunk size */);

            $lastWritePromise = $a->end($message);

            $a->close();

            $lastWritePromise;
        }));
    }

    public function testThrowsOnStreamNotWritable() {
        $this->expectException(StreamException::class);

        wait(async(function () {
            list($a, $b) = $this->getStreamPair();

            $message = \str_repeat(".", 8192 /* default chunk size */);

            $a->close();

            $lastWritePromise = $a->end($message);

            $lastWritePromise;
        }));
    }

    public function testThrowsOnReferencingClosedStream() {
        $this->expectException(\Error::class);

        wait(async(function () {
            list($a, $b) = $this->getStreamPair();

            $b->close();

            $b->reference();
        }));
    }

    public function testThrowsOnUnreferencingClosedStream() {
        $this->expectException(\Error::class);

        wait(async(function () {
            list($a, $b) = $this->getStreamPair();

            $b->close();

            $b->unreference();
        }));
    }

    public function testThrowsOnPendingRead() {
        $this->expectException(PendingReadError::class);

        wait(async(function () {
            list($a, $b) = $this->getStreamPair();

            $b->read();
            $b->read();
        }));
    }

    public function testResolveSuccessOnClosedStream() {
        wait(async(function () {
            list($a, $b) = $this->getStreamPair();

            $b->close();

            $this->assertInstanceOf(Success::class, $b->read());
        }));
    }

    public function testChunkedPayload() {
        wait(async(function () {
            list($a, $b) = $this->getStreamPair(4096);

            $message = \str_repeat(".", 8192 /* default chunk size */);

            \Amp\Promise\rethrow($a->end($message));

            $received = "";
            while (null !== $chunk = $b->read()) {
                $received .= $chunk;
            }

            $this->assertSame($message, $received);
        }));
    }

    public function testEmptyPayload() {
        wait(async(function () {
            list($a, $b) = $this->getStreamPair(4096);

            $message = "";

            \Amp\Promise\rethrow($a->end($message));

            $received = "";
            while (null !== $chunk = $b->read()) {
                $received .= $chunk;
            }

            $this->assertSame($message, $received);
        }));
    }

    public function testCloseStreamAfterEndPayload() {
        wait(async(function () {
            list($a, $b) = $this->getStreamPair();

            $message = \str_repeat(".", 8192 /* default chunk size */);

            \Amp\Promise\rethrow($a->end($message));

            $received = "";
            while (null !== $chunk = $b->read()) {
                $received .= $chunk;
            }

            $this->assertSame($message, $received);
        }));
    }
}
