<?php

namespace Amp\ByteStream\Test;

use Amp\AsyncGenerator;
use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\PipelineStream;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\ZlibInputStream;
use Amp\PHPUnit\AsyncTestCase;

class ZlibInputStreamTest extends AsyncTestCase
{
    public function testRead()
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

        $gzStream = new ZlibInputStream($stream, \ZLIB_ENCODING_GZIP);

        $buffer = "";
        while (($chunk = $gzStream->read()) !== null) {
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

        $gzStream->read();
    }
}
