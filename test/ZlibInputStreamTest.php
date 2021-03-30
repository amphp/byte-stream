<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\ZlibInputStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Producer;

class ZlibInputStreamTest extends AsyncTestCase
{
    public function testRead(): ?\Generator
    {
        $file1 = __DIR__ . "/fixtures/foobar.txt";
        $file2 = __DIR__ . "/fixtures/foobar.txt.gz";

        $stream = new IteratorStream(new Producer(function (callable $emit) use ($file2) {
            $content = \file_get_contents($file2);

            while ($content !== "") {
                yield $emit($content[0]);
                $content = \substr($content, 1);
            }
        }));

        $gzStream = new ZlibInputStream($stream, \ZLIB_ENCODING_GZIP);

        $buffer = "";
        while (($chunk = yield $gzStream->read()) !== null) {
            $buffer .= $chunk;
        }

        $expected = \str_replace("\r\n", "\n", \file_get_contents($file1));
        self::assertSame($expected, $buffer);
    }

    public function testGetEncoding(): void
    {
        $gzStream = new ZlibInputStream(new InMemoryStream(""), \ZLIB_ENCODING_GZIP);

        self::assertSame(\ZLIB_ENCODING_GZIP, $gzStream->getEncoding());
    }

    public function testInvalidEncoding(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->expectException(StreamException::class);
        } else {
            $this->expectException(\ValueError::class);
        }

        new ZlibInputStream(new InMemoryStream(""), 1337);
    }

    public function testGetOptions(): void
    {
        $options = [
            "level" => -1,
            "memory" => 8,
            "window" => 15,
            "strategy" => \ZLIB_DEFAULT_STRATEGY,
        ];

        $gzStream = new ZlibInputStream(new InMemoryStream(""), \ZLIB_ENCODING_GZIP, $options);

        self::assertSame($options, $gzStream->getOptions());
    }

    public function testInvalidStream(): ?\Generator
    {
        $this->expectException(StreamException::class);

        $gzStream = new ZlibInputStream(new InMemoryStream("Invalid"), \ZLIB_ENCODING_GZIP);

        yield $gzStream->read();
    }
}
