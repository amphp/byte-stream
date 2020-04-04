<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream;
use Amp\PHPUnit\AsyncTestCase;

class StdStreamTest extends AsyncTestCase
{
    public function testGetInputBufferStream()
    {
        $stream = ByteStream\getInputBufferStream();
        $this->assertSame($stream, ByteStream\getInputBufferStream());
    }

    public function testGetOutputBufferStream()
    {
        $stream = ByteStream\getOutputBufferStream();
        $this->assertSame($stream, ByteStream\getOutputBufferStream());
    }

    public function testGetStdin()
    {
        $stream = ByteStream\getStdin();
        $this->assertSame($stream, ByteStream\getStdin());
        $this->assertSame(\STDIN, $stream->getResource());
    }

    public function testGetStdout()
    {
        $stream = ByteStream\getStdout();
        $this->assertSame($stream, ByteStream\getStdout());
        $this->assertSame(\STDOUT, $stream->getResource());
    }

    public function testGetStderr()
    {
        $stream = ByteStream\getStderr();
        $this->assertSame($stream, ByteStream\getStderr());
        $this->assertSame(\STDERR, $stream->getResource());
    }
}
