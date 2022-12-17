<?php declare(strict_types=1);

namespace Amp\ByteStream\Base64;

use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline\Queue;
use function Amp\async;
use function Amp\ByteStream\buffer;

final class Base64DecodingInputStreamTest extends AsyncTestCase
{
    private Queue $source;

    private ReadableStream $stream;

    public function testRead(): void
    {
        $future = async(fn () => buffer($this->stream));

        $this->source->pushAsync('Z');
        $this->source->pushAsync('m9vLmJhcg=');
        $this->source->pushAsync('=');
        $this->source->complete();

        self::assertSame('foo.bar', $future->await());
    }

    public function testInvalidDataMissingPadding(): void
    {
        $future = async(fn () => buffer($this->stream));

        $this->source->pushAsync('Z');
        $this->source->pushAsync('m9vLmJhcg=');
        $this->source->pushAsync(''); // missing =
        $this->source->complete();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Failed to read stream chunk due to invalid base64 data');

        self::assertSame('foo.bar', $future->await());
    }

    public function testInvalidDataChar(): void
    {
        $future = async(fn () => buffer($this->stream));

        $this->source->pushAsync('Z');
        $this->source->pushAsync('!');
        $this->source->complete();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Failed to read stream chunk due to invalid base64 data');

        self::assertSame('foo.bar', $future->await());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = new Queue;
        $this->stream = new Base64DecodingReadableStream(new ReadableIterableStream($this->source->pipe()));
    }
}
