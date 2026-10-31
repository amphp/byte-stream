<?php declare(strict_types=1);

namespace Amp\ByteStream;

use function Amp\async;
use function Amp\delay;

final class ReadableResourceStreamTest extends TlsStreamTestCase
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

    public function testTlsStreamReadAndWrite(): void
    {
        $this->setTimeout(2);

        [$read, $write] = $this->createTlsPair();

        $input = new ReadableResourceStream($read);
        $output = new WritableResourceStream($write);

        $output->write('test');

        self::assertFalse(\feof($read), 'feof should return false, but will buffer data');

        $data = $input->read();

        self::assertSame('test', $data);

        $future = async(fn () => $input->read());

        delay(0.1); // Allow read operation above to suspend in event-loop.

        self::assertFalse($future->isComplete());

        $output->write('test');
        $output->close();

        self::assertFalse(\feof($read), 'feof should return false, but will buffer data');

        self::assertSame('test', $future->await());
        self::assertNull($input->read());

        self::assertTrue(\feof($read));
    }
}
