<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\ByteStream\StreamException;
use Amp\Loop;
use PHPUnit\Framework\TestCase;

class ResourceStreamTest extends TestCase {
    const LARGE_MESSAGE_SIZE = 1 << 20; // 1 MB

    public function getStreamPair() {
        $domain = \stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX;
        list($left, $right) = @\stream_socket_pair($domain, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

        $a = new ResourceOutputStream($left);
        $b = new ResourceInputStream($right);

        return [$a, $b];
    }

    public function testLargePayloads() {
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

    public function testManySmallPayloads() {
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

    public function testThrowsOnExternallyShutdownStreamWithLargePayload() {
        $this->expectException(StreamException::class);

        Loop::run(function () {
            try { /* prevent crashes with phpdbg due to SIGPIPE not being handled... */
                Loop::onSignal(defined("SIGPIPE") ? SIGPIPE : 13, function () {});
            } catch (Loop\UnsupportedFeatureException $e) {}

            list($a, $b) = $this->getStreamPair();

            $message = \str_repeat(".", self::LARGE_MESSAGE_SIZE);

            $writePromise = $a->write($message);

            yield $b->read();
            $b->close();

            yield $writePromise;
        });
    }

    public function testThrowsOnExternallyShutdownStreamWithSmallPayloads() {
        $this->expectException(StreamException::class);

        Loop::run(function () {
            try { /* prevent crashes with phpdbg due to SIGPIPE not being handled... */
                Loop::onSignal(defined("SIGPIPE") ? SIGPIPE : 13, function () {});
            } catch (Loop\UnsupportedFeatureException $e) {}

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
}
