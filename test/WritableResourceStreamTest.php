<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use function Amp\delay;

final class WritableResourceStreamTest extends AsyncTestCase
{
    public function testGetResource(): void
    {
        $stream = new WritableResourceStream(\STDOUT);

        self::assertSame(\STDOUT, $stream->getResource());
    }

    public function testNonStream(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a valid stream");

        /** @noinspection PhpParamsInspection */
        new WritableResourceStream(42);
    }

    public function testNotWritable(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a writable stream");

        new WritableResourceStream(\STDIN);
    }

    public function testBrokenPipe(): void
    {
        if (($sockets = @\stream_socket_pair(
            \PHP_OS_FAMILY === 'Windows' ? STREAM_PF_INET : STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP
        )) === false) {
            self::fail("Failed to create socket pair.");
        }

        [$a, $b] = $sockets;

        $stream = new WritableResourceStream($a);
        \fclose($b);

        $this->expectException(StreamException::class);

        if (\PHP_OS_FAMILY === 'Windows') {
            $this->expectExceptionMessage(/* S|s */ 'end of 6 bytes failed with errno=10053 An established connection was aborted by the software in your host machine');
        } else {
            $this->expectExceptionMessage(/* S|s */ "end of 6 bytes failed with errno=32 Broken pipe");
        }

        $stream->write("foobar");
    }

    public function testClosedRemoteSocket(): void
    {
        $server = \stream_socket_server("tcp://127.0.0.1:0");
        $address = \stream_socket_get_name($server, false);

        $a = \stream_socket_client("tcp://" . $address);
        $b = \stream_socket_accept($server);

        $stream = new WritableResourceStream($a);
        \fclose($b);

        $this->expectException(StreamException::class);

        if (\PHP_OS_FAMILY === 'Windows') {
            $this->expectExceptionMessage(/* S|s */ 'end of 6 bytes failed with errno=10053 An established connection was aborted by the software in your host machine');
        } else {
            $this->expectExceptionMessage(/* S|s */ "end of 6 bytes failed with errno=32 Broken pipe");
        }

        // The first write still succeeds somehow...
        $stream->write("foobar");
        delay(0.1); // Provide some time for the OS to mark the socket is closed.
        $stream->write("foobar");
    }
}
