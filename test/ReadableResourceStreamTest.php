<?php declare(strict_types=1);

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;

final class ReadableResourceStreamTest extends AsyncTestCase
{
    public function testGetResource(): void
    {
        $stream = new ReadableResourceStream(\STDIN);

        self::assertSame(\STDIN, $stream->getResource());
    }

    public function testNonStream(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a valid stream");

        /** @noinspection PhpParamsInspection */
        new ReadableResourceStream(42);
    }

    public function testNotReadable(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a readable stream");

        new ReadableResourceStream(\STDOUT);
    }

    public function testClosedRemoteSocketWithFork(): void
    {
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

        $stream = new ReadableResourceStream($a);
        \stream_socket_shutdown($b, \STREAM_SHUT_RDWR);
        \fclose($b);

        try {
            // Read must succeed before the sub-process exits
            $start = \microtime(true);
            self::assertNull($stream->read());
            self::assertLessThanOrEqual(1, \microtime(true) - $start);
        } finally {
            \proc_terminate($proc);
            \proc_close($proc);
        }
    }
}
