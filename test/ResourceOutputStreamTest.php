<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\ByteStream\StreamException;
use Amp\Delayed;
use Amp\PHPUnit\AsyncTestCase;

class ResourceOutputStreamTest extends AsyncTestCase
{
    public function testGetResource()
    {
        $stream = new ResourceOutputStream(\STDOUT);

        $this->assertSame(\STDOUT, $stream->getResource());
    }

    public function testNonStream()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a valid stream");

        new ResourceOutputStream(42);
    }

    public function testNotWritable()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Expected a writable stream");

        new ResourceOutputStream(\STDIN);
    }

    public function testBrokenPipe()
    {
        if (($sockets = @\stream_socket_pair(
            \stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP
        )) === false) {
            $this->fail("Failed to create socket pair.");
        }

        list($a, $b) = $sockets;

        $stream = new ResourceOutputStream($a);
        \fclose($b);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage("fwrite(): send of 6 bytes failed with errno=32 Broken pipe");

        yield $stream->write("foobar");
    }

    public function testClosedRemoteSocket()
    {
        $server = \stream_socket_server("tcp://127.0.0.1:0");
        $address = \stream_socket_get_name($server, false);

        $a = \stream_socket_client("tcp://" . $address);
        $b = \stream_socket_accept($server);

        $stream = new ResourceOutputStream($a);
        \fclose($b);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage("fwrite(): send of 6 bytes failed with errno=32 Broken pipe");

        // The first write still succeeds somehow...
        yield $stream->write("foobar");

        // A delay seems required for the OS to realize the socket is indeed closed.
        yield new Delayed(10);

        yield $stream->write("foobar");
    }

    /**
     * @requires PHPUnit >= 7
     *
     * @see https://github.com/reactphp/stream/pull/150
     */
    public function testUploadBiggerBlockSecure()
    {
        $size = 2 ** 18; // 256kb

        $resource = \stream_socket_client('tls://httpbin.org:443');

        $output = new ResourceOutputStream($resource);

        $body = \str_repeat('.', $size);

        yield $output->write("POST /post HTTP/1.0\r\nHost: httpbin.org\r\nContent-Length: $size\r\n\r\n" . $body);

        $input = new ResourceInputStream($resource);
        $buffer = '';
        while (null !== ($chunk = yield $input->read())) {
            $buffer .= $chunk;
        }

        $this->assertStringContainsString($body, $buffer);
    }
}
