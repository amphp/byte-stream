<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Amp\ByteStream\Compression;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\StreamException;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\ByteStream\buffer;

final class DecompressingReadableStreamTest extends AsyncTestCase
{
    public function testRead(): void
    {
        $file1 = __DIR__ . "/../fixtures/foobar.txt";
        $file2 = __DIR__ . "/../fixtures/foobar.txt.gz";

        $contents = \file_get_contents($file2);

        $stream = new ReadableIterableStream(\str_split($contents));
        $gzStream = new DecompressingReadableStream($stream, \ZLIB_ENCODING_GZIP);

        $expected = \str_replace("\r\n", "\n", \file_get_contents($file1));
        self::assertSame($expected, buffer($gzStream));
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
