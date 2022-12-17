<?php declare(strict_types=1);
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Amp\ByteStream\Compression;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableIterableStream;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\ByteStream\buffer;

final class CompressingReadableStreamTest extends AsyncTestCase
{
    public function testRead(): void
    {
        $file = __DIR__ . "/../fixtures/foobar.txt";
        $contents = \file_get_contents($file);

        $stream = new ReadableIterableStream(\str_split($contents));

        $gzStream = new CompressingReadableStream($stream, \ZLIB_ENCODING_GZIP);
        $readableStream = new DecompressingReadableStream($gzStream, \ZLIB_ENCODING_GZIP);

        self::assertStringEqualsFile($file, buffer($readableStream));
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
