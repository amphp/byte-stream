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
    public function testRead()
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
        $this->assertSame($expected, $buffer);
    }

    public function testGetEncoding()
    {
        $gzStream = new ZlibInputStream(new InMemoryStream(""), \ZLIB_ENCODING_GZIP);

        $this->assertSame(\ZLIB_ENCODING_GZIP, $gzStream->getEncoding());
    }

    public function testInvalidEncoding()
    {
        $this->expectException(StreamException::class);

        new ZlibInputStream(new InMemoryStream(""), 1337);
    }

    public function testGetOptions()
    {
        $options = [
            "level" => -1,
            "memory" => 8,
            "window" => 15,
            "strategy" => \ZLIB_DEFAULT_STRATEGY,
        ];

        $gzStream = new ZlibInputStream(new InMemoryStream(""), \ZLIB_ENCODING_GZIP, $options);

        $this->assertSame($options, $gzStream->getOptions());
    }

    public function testInvalidStream()
    {
        $this->expectException(StreamException::class);

        $gzStream = new ZlibInputStream(new InMemoryStream("Invalid"), \ZLIB_ENCODING_GZIP);

        yield $gzStream->read();
    }
}
