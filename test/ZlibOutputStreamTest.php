<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\OutputBuffer;
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\ZlibInputStream;
use Amp\ByteStream\ZlibOutputStream;
use Amp\PHPUnit\AsyncTestCase;

class ZlibOutputStreamTest extends AsyncTestCase
{
    public function testWrite(): ?\Generator
    {
        $file1 = __DIR__ . "/fixtures/foobar.txt";
        $file2 = __DIR__ . "/fixtures/foobar.txt.gz";

        $bufferStream = new OutputBuffer();
        $outputStream = new ZlibOutputStream($bufferStream, \ZLIB_ENCODING_GZIP);

        $fileStream = new ResourceInputStream(\fopen($file1, "r"));
        while (($chunk = yield $fileStream->read()) !== null) {
            yield $outputStream->write($chunk);
        }

        yield $outputStream->end();

        $inputStream = new ZlibInputStream(new InMemoryStream(yield $bufferStream), \ZLIB_ENCODING_GZIP);

        $buffer = "";
        while (($chunk = yield $inputStream->read()) !== null) {
            $buffer .= $chunk;
        }

        self::assertSame(\file_get_contents($file1), $buffer);
    }

    public function testThrowsOnWritingToClosedContext(): void
    {
        $this->expectException(ClosedException::class);

        $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP);
        $gzStream->end("foo");
        $gzStream->write("bar");
    }

    public function testThrowsOnEndingToClosedContext(): void
    {
        $this->expectException(ClosedException::class);

        $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP);
        $gzStream->end("foo");
        $gzStream->end("bar");
    }

    public function testGetEncoding(): void
    {
        $gzStream = new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP);

        self::assertSame(\ZLIB_ENCODING_GZIP, $gzStream->getEncoding());
    }

    public function testInvalidEncoding(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->expectException(StreamException::class);
        } else {
            $this->expectException(\ValueError::class);
        }

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

        self::assertSame($options, $gzStream->getOptions());
    }

    public function testInvalidOptions(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->expectException(StreamException::class);
        } else {
            $this->expectException(\ValueError::class);
        }

        new ZlibOutputStream(new OutputBuffer(), \ZLIB_ENCODING_GZIP, ["level" => 42]);
    }
}
