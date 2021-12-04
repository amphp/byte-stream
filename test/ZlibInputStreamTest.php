<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\PipelineStream;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\ZlibReadableStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline\AsyncGenerator;

class ZlibInputStreamTest extends AsyncTestCase
{
    public function testRead(): void
    {
        $file1 = __DIR__ . "/fixtures/foobar.txt";
        $file2 = __DIR__ . "/fixtures/foobar.txt.gz";

        $stream = new PipelineStream(new AsyncGenerator(function () use ($file2) {
            $content = \file_get_contents($file2);

            while (\strlen($content)) {
                yield $content[0];
                $content = \substr($content, 1);
            }
        }));

        $gzStream = new ZlibReadableStream($stream, \ZLIB_ENCODING_GZIP);

        $buffer = "";
        while (($chunk = $gzStream->read()) !== null) {
            $buffer .= $chunk;
        }

        $expected = \str_replace("\r\n", "\n", \file_get_contents($file1));
        self::assertSame($expected, $buffer);
    }

    public function testGetEncoding(): void
    {
        $gzStream = new ZlibReadableStream(new InMemoryStream(""), \ZLIB_ENCODING_GZIP);

        self::assertSame(\ZLIB_ENCODING_GZIP, $gzStream->getEncoding());
    }

    public function testInvalidEncoding(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->expectException(StreamException::class);
        } else {
            $this->expectException(\ValueError::class);
        }

        new ZlibReadableStream(new InMemoryStream(""), 1337);
    }

    public function testGetOptions(): void
    {
        $options = [
            "level" => -1,
            "memory" => 8,
            "window" => 15,
            "strategy" => \ZLIB_DEFAULT_STRATEGY,
        ];

        $gzStream = new ZlibReadableStream(new InMemoryStream(""), \ZLIB_ENCODING_GZIP, $options);

        self::assertSame($options, $gzStream->getOptions());
    }

    public function testInvalidStream(): void
    {
        $this->expectException(StreamException::class);

        $gzStream = new ZlibReadableStream(new InMemoryStream("Invalid"), \ZLIB_ENCODING_GZIP);

        $gzStream->read();
    }
}
