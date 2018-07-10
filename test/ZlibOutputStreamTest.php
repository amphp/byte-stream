<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\OutputBuffer;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\ZlibInputStream;
use Amp\ByteStream\ZlibOutputStream;
use Amp\Iterator;
use Amp\PHPUnit\TestCase;
use Concurrent\Task;
use function Amp\Promise\await;

class ZlibOutputStreamTest extends TestCase
{
    public function testWrite(): void
    {
        Task::await(Task::async(function () {
            $file1 = __DIR__ . "/fixtures/foobar.txt";

            $bufferStream = new OutputBuffer;
            $outputStream = new ZlibOutputStream($bufferStream, \ZLIB_ENCODING_GZIP);

            $input = \file_get_contents($file1);
            $inputStream = new IteratorStream(Iterator\fromIterable(str_split($input, 1)));
            while (($chunk = $inputStream->read()) !== null) {
                $outputStream->write($chunk);
            }

            $outputStream->end();

            $inputStream = new ZlibInputStream(new InMemoryStream(await($bufferStream)), \ZLIB_ENCODING_GZIP);

            $buffer = "";
            while (($chunk = $inputStream->read()) !== null) {
                $buffer .= $chunk;
            }

            $this->assertStringEqualsFile($file1, $buffer);
        }));
    }

    public function testThrowsOnWritingToClosedContext(): void
    {
        $this->expectException(ClosedException::class);

        Task::await(Task::async(function () {
            $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP);
            $gzStream->end("foo");
            $gzStream->write("bar");
        }));
    }

    public function testThrowsOnEndingToClosedContext(): void
    {
        $this->expectException(ClosedException::class);

        Task::await(Task::async(function () {
            $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP);
            $gzStream->end("foo");
            $gzStream->end("bar");
        }));
    }

    public function testGetEncoding(): void
    {
        $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP);

        $this->assertSame(\ZLIB_ENCODING_GZIP, $gzStream->getEncoding());
    }

    public function testInvalidEncoding(): void
    {
        $this->expectException(StreamException::class);

        new ZlibOutputStream(new OutputBuffer(), 1337);
    }

    public function testGetOptions(): void
    {
        $options = [
            "level" => -1,
            "memory" => 8,
            "window" => 15,
            "strategy" => \ZLIB_DEFAULT_STRATEGY,
        ];

        $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP, $options);

        $this->assertSame($options, $gzStream->getOptions());
    }

    public function testInvalidOptions(): void
    {
        $this->expectException(StreamException::class);

        new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP, ["level" => 42]);
    }
}
