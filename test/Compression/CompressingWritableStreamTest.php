<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Amp\ByteStream\Compression;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\WritableBuffer;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\ByteStream\pipe;

final class CompressingWritableStreamTest extends AsyncTestCase
{
    public function testWrite(): void
    {
        $file = __DIR__ . "/../fixtures/foobar.txt";

        $bufferStream = new WritableBuffer();
        $writableStream = new CompressingWritableStream($bufferStream, \ZLIB_ENCODING_GZIP);

        $fileStream = new ReadableResourceStream(\fopen($file, 'rb'));
        pipe($fileStream, $writableStream);
        $writableStream->end();

        $inputStream = new DecompressingReadableStream(new ReadableBuffer($bufferStream->buffer()), \ZLIB_ENCODING_GZIP);

        $buffer = "";
        while (($chunk = $inputStream->read()) !== null) {
            $buffer .= $chunk;
        }

        self::assertStringEqualsFile($file, $buffer);
    }

    public function testThrowsOnWritingToClosedContext(): void
    {
        $this->expectException(ClosedException::class);

        $gzStream = new CompressingWritableStream(new WritableBuffer(), \ZLIB_ENCODING_GZIP);
        $gzStream->write('foo');
        $gzStream->end();
        $gzStream->write('bar');
    }

    public function testThrowsOnEndingToClosedContext(): void
    {
        $this->expectException(ClosedException::class);

        $gzStream = new CompressingWritableStream(new WritableBuffer(), \ZLIB_ENCODING_GZIP);
        $gzStream->end();
        $gzStream->end();
    }

    public function testGetEncoding(): void
    {
        $gzStream = new CompressingWritableStream(new WritableBuffer(), \ZLIB_ENCODING_GZIP);

        self::assertSame(\ZLIB_ENCODING_GZIP, $gzStream->getEncoding());
    }

    public function testInvalidEncoding(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->expectException(StreamException::class);
        } else {
            $this->expectException(\ValueError::class);
        }

        new CompressingWritableStream(new WritableBuffer(), 1337);
    }

    public function testGetOptions(): void
    {
        $options = [
            "level" => -1,
            "memory" => 8,
            "window" => 15,
            "strategy" => \ZLIB_DEFAULT_STRATEGY,
        ];

        $gzStream = new CompressingWritableStream(new WritableBuffer(), \ZLIB_ENCODING_GZIP, $options);

        self::assertSame($options, $gzStream->getOptions());
    }

    public function testInvalidOptions(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->expectException(StreamException::class);
        } else {
            $this->expectException(\ValueError::class);
        }

        new CompressingWritableStream(new WritableBuffer(), \ZLIB_ENCODING_GZIP, ["level" => 42]);
    }
}
