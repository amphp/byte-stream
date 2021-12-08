<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Amp\ByteStream\Compression;

use Amp\ByteStream\IterableStream;
use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\StreamException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline\AsyncGenerator;

final class DecompressingReadableStreamTest extends AsyncTestCase
{
    public function testRead(): void
    {
        $file1 = __DIR__ . "/../fixtures/foobar.txt";
        $file2 = __DIR__ . "/../fixtures/foobar.txt.gz";

        $stream = new IterableStream(new AsyncGenerator(function () use ($file2) {
            $content = \file_get_contents($file2);

            while ($content !== '') {
                yield $content[0];
                $content = \substr($content, 1);
            }
        }));

        $gzStream = new DecompressingReadableStream($stream, \ZLIB_ENCODING_GZIP);

        $buffer = "";
        while (($chunk = $gzStream->read()) !== null) {
            $buffer .= $chunk;
        }

        $expected = \str_replace("\r\n", "\n", \file_get_contents($file1));
        self::assertSame($expected, $buffer);
    }

    public function testGetEncoding(): void
    {
        $gzStream = new DecompressingReadableStream(new ReadableBuffer(""), \ZLIB_ENCODING_GZIP);

        self::assertSame(\ZLIB_ENCODING_GZIP, $gzStream->getEncoding());
    }

    public function testInvalidEncoding(): void
    {
        $this->expectException(\ValueError::class);

        new DecompressingReadableStream(new ReadableBuffer(""), 1337);
    }

    public function testGetOptions(): void
    {
        $options = [
            "level" => -1,
            "memory" => 8,
            "window" => 15,
            "strategy" => \ZLIB_DEFAULT_STRATEGY,
        ];

        $gzStream = new DecompressingReadableStream(new ReadableBuffer(""), \ZLIB_ENCODING_GZIP, $options);

        self::assertSame($options, $gzStream->getOptions());
    }

    public function testInvalidStream(): void
    {
        $this->expectException(StreamException::class);

        $gzStream = new DecompressingReadableStream(new ReadableBuffer("Invalid"), \ZLIB_ENCODING_GZIP);

        $gzStream->read();
    }
}
