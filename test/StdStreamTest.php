<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream;
use Amp\PHPUnit\AsyncTestCase;

class StdStreamTest extends AsyncTestCase
{
    public function testGetInputBufferStream(): void
    {
        $stream = ByteStream\getInputBufferStream();
        self::assertSame($stream, ByteStream\getInputBufferStream());
    }

    public function testGetOutputBufferStream(): void
    {
        $stream = ByteStream\getOutputBufferStream();
        self::assertSame($stream, ByteStream\getOutputBufferStream());
    }

    public function testGetStdin(): void
    {
        $stream = ByteStream\getStdin();
        self::assertSame($stream, ByteStream\getStdin());
        self::assertSame(\STDIN, $stream->getResource());
    }

    public function testGetStdout(): void
    {
        $stream = ByteStream\getStdout();
        self::assertSame($stream, ByteStream\getStdout());
        self::assertSame(\STDOUT, $stream->getResource());
    }

    public function testGetStderr(): void
    {
        $stream = ByteStream\getStderr();
        self::assertSame($stream, ByteStream\getStderr());
        self::assertSame(\STDERR, $stream->getResource());
    }
}
