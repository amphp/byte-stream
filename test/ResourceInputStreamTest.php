<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ResourceInputStream;
use Amp\PHPUnit\TestCase;

class ResourceInputStreamTest extends TestCase {
    public function testCloseOnUniFileStream() {
        $resource = \fopen(__FILE__, "r");
        $stream = new ResourceInputStream($resource);

        $stream->close();

        $result = 42;

        $stream->read()->onResolve(function ($e, $r) use (&$result) {
            $this->assertNull($e);
            $result = $r;
        });

        $this->assertNull($result);
        $this->assertFalse(@\fread($resource, 8192));
    }

    public function testCloseOnDuplexFileStream() {
        $resource = \fopen(__FILE__, "a+");
        $stream = new ResourceInputStream($resource);

        $stream->close();

        $result = 42;

        $stream->read()->onResolve(function ($e, $r) use (&$result) {
            $this->assertNull($e);
            $result = $r;
        });

        $this->assertNull($result);
        $this->assertFalse(@\fread($resource, 8192));

        // Files do not support half-closes
        $this->assertFalse(@\fwrite($resource, "foobar"));
    }

    public function testCloseOnTcpStream() {
        $resource = \stream_socket_client("tcp://github.com:80", $errno, $errstr, 10, \STREAM_CLIENT_CONNECT);

        $this->assertSame(0, $errno);
        $this->assertSame("", $errstr);
        $this->assertInternalType("resource", $resource);

        $stream = new ResourceInputStream($resource);
        $stream->close();

        $result = 42;

        $stream->read()->onResolve(function ($e, $r) use (&$result) {
            $this->assertNull($e);
            $result = $r;
        });

        $this->assertNull($result);
        $this->assertSame("", \fread($resource, 8192));

        // Tcp does support half-closes
        $this->assertNotFalse(@\fwrite($resource, "foobar"));
    }
}
