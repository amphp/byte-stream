<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ResourceOutputStream;
use Amp\ByteStream\StreamException;
use PHPUnit\Framework\TestCase;
use function Amp\Promise\wait;

class ResourceOutputStreamTest extends TestCase {
    public function testGetResource() {
        $stream = new ResourceOutputStream(\STDOUT);

        $this->assertSame(\STDOUT, $stream->getResource());
    }

    public function testNonStream() {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a valid stream");

        new ResourceOutputStream(42);
    }

    public function testNotWritable() {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a writable stream");

        new ResourceOutputStream(\STDIN);
    }

    public function testBrokenPipe() {
        if (($sockets = @\stream_socket_pair(\stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)) === false) {
            $this->fail("Failed to create socket pair.");
        }

        list($a, $b) = $sockets;

        $stream = new ResourceOutputStream($a);
        \fclose($b);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage("Failed to write to stream; fwrite():");
        wait($stream->write("foobar"));
    }

    public function testClosedRemoteSocket() {
        $server = \stream_socket_server("tcp://127.0.0.1:0");
        $address = \stream_socket_get_name($server, false);

        $a = \stream_socket_client("tcp://" . $address);
        $b = \stream_socket_accept($server);

        $stream = new ResourceOutputStream($a);
        \fclose($b);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage("Failed to write to stream; fwrite():");

        // The first write still succeeds somehow...
        wait($stream->write("foobar"));
        wait($stream->write("foobar"));
    }
}
