<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Amp\ByteStream\Compression;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableIterableStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline\Pipeline;

final class CompressingReadableStreamTest extends AsyncTestCase
{
    public function testRead(): void
    {
        $file = __DIR__ . "/../fixtures/foobar.txt";

        $stream = new ReadableIterableStream(Pipeline::fromIterable(function () use ($file) {
            $content = \file_get_contents($file);

            while ($content !== '') {
                yield $content[0];
                $content = \substr($content, 1);
            }
        }));

        $gzStream = new CompressingReadableStream($stream, \ZLIB_ENCODING_GZIP);
        $inputStream = new DecompressingReadableStream($gzStream, \ZLIB_ENCODING_GZIP);

        $buffer = "";
        while (($chunk = $inputStream->read()) !== null) {
            $buffer .= $chunk;
        }

        self::assertStringEqualsFile($file, $buffer);
    }

    public function testGetEncoding(): void
    {
        $gzStream = new CompressingReadableStream(new ReadableBuffer(""), \ZLIB_ENCODING_GZIP);

        self::assertSame(\ZLIB_ENCODING_GZIP, $gzStream->getEncoding());
    }

    public function testInvalidEncoding(): void
    {
        $this->expectException(\ValueError::class);

        new CompressingReadableStream(new ReadableBuffer(""), 1337);
    }

    public function testGetOptions(): void
    {
        $options = [
            "level" => -1,
            "memory" => 8,
            "window" => 15,
            "strategy" => \ZLIB_DEFAULT_STRATEGY,
        ];

        $gzStream = new CompressingReadableStream(new ReadableBuffer(""), \ZLIB_ENCODING_GZIP, $options);

        self::assertSame($options, $gzStream->getOptions());
    }
}
