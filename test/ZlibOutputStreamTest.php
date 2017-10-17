<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\OutputBuffer;
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\ZlibInputStream;
use Amp\ByteStream\ZlibOutputStream;
use Amp\Loop;
use Amp\PHPUnit\TestCase;

class ZlibOutputStreamTest extends TestCase {
    public function testWrite() {
        Loop::run(function () {
            $file1 = __DIR__ . "/fixtures/foobar.txt";
            $file2 = __DIR__ . "/fixtures/foobar.txt.gz";

            $bufferStream = new OutputBuffer();
            $outputStream = new ZlibOutputStream($bufferStream, \ZLIB_ENCODING_GZIP);

            $fileStream = new ResourceInputStream(fopen($file1, "r"));
            while (($chunk = yield $fileStream->read()) !== null) {
                yield $outputStream->write($chunk);
            }

            yield $outputStream->end();

            $inputStream = new ZlibInputStream(new InMemoryStream(yield $bufferStream), \ZLIB_ENCODING_GZIP);

            $buffer = "";
            while (($chunk = yield $inputStream->read()) !== null) {
                $buffer .= $chunk;
            }

            $this->assertSame(\file_get_contents($file1), $buffer);
        });
    }

    public function testThrowsOnWritingToClosedContext() {
        $this->expectException(ClosedException::class);

        Loop::run(function () {
            $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP);
            $gzStream->end("foo");
            $gzStream->write("bar");
        });
    }

    public function testThrowsOnEndingToClosedContext() {
        $this->expectException(ClosedException::class);

        Loop::run(function () {
            $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP);
            $gzStream->end("foo");
            $gzStream->end("bar");
        });
    }

    public function testGetEncoding() {
        $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP);

        $this->assertSame(\ZLIB_ENCODING_GZIP, $gzStream->getEncoding());
    }

    public function testInvalidEncoding() {
        $this->expectException(StreamException::class);

        new ZlibOutputStream(new OutputBuffer(), 1337);
    }

    public function testGetOptions() {
        $options = [
            "level" => -1,
            "memory" => 8,
            "window" => 15,
            "strategy" => \ZLIB_DEFAULT_STRATEGY,
        ];

        $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP, $options);

        $this->assertSame($options, $gzStream->getOptions());
    }

    public function testInvalidOptions() {
        $this->expectException(StreamException::class);

        new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP, ["level" => 42]);
    }
}
