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
        $this->expectExceptionMessage("The stream was closed by the peer");
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
        $this->expectExceptionMessage("The stream was closed by the peer");

        // The first write still succeeds somehow...
        wait($stream->write("foobar"));
        wait($stream->write("foobar"));
    }

    public function testClosedRemoteSocketWithFork() {
        $server = \stream_socket_server("tcp://127.0.0.1:0");
        $address = \stream_socket_get_name($server, false);

        $a = \stream_socket_client("tcp://" . $address);
        $b = \stream_socket_accept($server);

        // Creates a fork without having to deal with it…
        // The fork inherits the FDs of the current process.
        $proc = \proc_open("sleep 3", [
            ['pipe', 'r'],
            ['pipe', 'w'],
            ['pipe', 'w'],
        ], $pipes);

        $stream = new ResourceOutputStream($a);
        \stream_socket_shutdown($b, \STREAM_SHUT_RDWR);
        \fclose($b);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage("The stream was closed by the peer");

        try {
            // The first write still succeeds somehow...
            wait($stream->write("foobar"));
            wait($stream->write("foobar"));
        } finally {
            \proc_terminate($proc);
            \proc_close($proc);
        }
    }
}
