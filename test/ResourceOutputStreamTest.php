<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ResourceOutputStream;
use Amp\ByteStream\StreamException;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\delay;

class ResourceOutputStreamTest extends AsyncTestCase
{
    public function testGetResource(): void
    {
        $stream = new ResourceOutputStream(\STDOUT);

        self::assertSame(\STDOUT, $stream->getResource());
    }

    public function testNonStream(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a valid stream");

        new ResourceOutputStream(42);
    }

    public function testNotWritable(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a writable stream");

        new ResourceOutputStream(\STDIN);
    }

    public function testBrokenPipe(): void
    {
        if (($sockets = @\stream_socket_pair(
            \stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP
        )) === false) {
            self::fail("Failed to create socket pair.");
        }

        [$a, $b] = $sockets;

        $stream = new ResourceOutputStream($a);
        \fclose($b);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage(/* S|s */ "end of 6 bytes failed with errno=32 Broken pipe");

        $stream->write("foobar")->await();
    }

    public function testClosedRemoteSocket(): void
    {
        $server = \stream_socket_server("tcp://127.0.0.1:0");
        $address = \stream_socket_get_name($server, false);

        $a = \stream_socket_client("tcp://" . $address);
        $b = \stream_socket_accept($server);

        $stream = new ResourceOutputStream($a);
        \fclose($b);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage(/* S|s */ "end of 6 bytes failed with errno=32 Broken pipe");

        // The first write still succeeds somehow...
        $stream->write("foobar")->await();
        delay(0.1); // Provide some time for the OS to mark the socket is closed.
        $stream->write("foobar")->await();
    }
}
