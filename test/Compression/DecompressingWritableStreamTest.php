<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Amp\ByteStream\Compression;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableBuffer;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\ByteStream\pipe;

final class DecompressingWritableStreamTest extends AsyncTestCase
{
    public function testWrite(): void
    {
        $file1 = __DIR__ . "/../fixtures/foobar.txt.gz";
        $file2 = __DIR__ . "/../fixtures/foobar.txt";

        $bufferStream = new WritableBuffer();
        $writableStream = new DecompressingWritableStream($bufferStream, \ZLIB_ENCODING_GZIP);

        $fileStream = new ReadableResourceStream(\fopen($file1, 'rb'));
        pipe($fileStream, $writableStream);
        $writableStream->end();

        self::assertStringEqualsFile($file2, $bufferStream->buffer());
    }

    public function testThrowsOnWritingToClosedContext(): void
    {
        $this->expectException(ClosedException::class);

        $gzStream = new DecompressingWritableStream(new WritableBuffer(), \ZLIB_ENCODING_GZIP);
        $gzStream->write(\gzencode('foo'));
        $gzStream->end();
        $gzStream->write('');
    }

    public function testThrowsOnEndingToClosedContext(): void
    {
        $this->expectException(ClosedException::class);

        $gzStream = new DecompressingWritableStream(new WritableBuffer(), \ZLIB_ENCODING_GZIP);
        $gzStream->end();
        $gzStream->end();
    }

    public function testGetEncoding(): void
    {
        $gzStream = new DecompressingWritableStream(new WritableBuffer(), \ZLIB_ENCODING_GZIP);

        self::assertSame(\ZLIB_ENCODING_GZIP, $gzStream->getEncoding());
    }

    public function testInvalidEncoding(): void
    {
        $this->expectException(\ValueError::class);

        new DecompressingWritableStream(new WritableBuffer(), 1337);
    }

    public function testGetOptions(): void
    {
        $options = [
            "level" => -1,
            "memory" => 8,
            "window" => 15,
            "strategy" => \ZLIB_DEFAULT_STRATEGY,
        ];

        $gzStream = new DecompressingWritableStream(new WritableBuffer(), \ZLIB_ENCODING_GZIP, $options);

        self::assertSame($options, $gzStream->getOptions());
    }
}
