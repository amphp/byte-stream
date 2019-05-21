<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream;
use Amp\Loop;
use Amp\PHPUnit\TestCase;

class StdStreamTest extends TestCase
{
    public function testGetInput()
    {
        Loop::run(function () {
            $stream = ByteStream\getInput();
            $this->assertSame($stream, ByteStream\getInput());
        });
    }

    public function testGetOutput()
    {
        Loop::run(function () {
            $stream = ByteStream\getOutput();
            $this->assertSame($stream, ByteStream\getOutput());
        });
    }

    public function testGetStdin()
    {
        Loop::run(function () {
            $stream = ByteStream\getStdin();
            $this->assertSame($stream, ByteStream\getStdin());
            $this->assertSame(\STDIN, $stream->getResource());
        });
    }

    public function testGetStdout()
    {
        Loop::run(function () {
            $stream = ByteStream\getStdout();
            $this->assertSame($stream, ByteStream\getStdout());
            $this->assertSame(\STDOUT, $stream->getResource());
        });
    }

    public function testGetStderr()
    {
        Loop::run(function () {
            $stream = ByteStream\getStderr();
            $this->assertSame($stream, ByteStream\getStderr());
            $this->assertSame(\STDERR, $stream->getResource());
        });
    }
}
