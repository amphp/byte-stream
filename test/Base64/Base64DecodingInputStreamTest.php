<?php

namespace Amp\ByteStream\Base64;

use Amp\ByteStream\IterableStream;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline\Emitter;
use function Amp\async;
use function Amp\ByteStream\buffer;

class Base64DecodingInputStreamTest extends AsyncTestCase
{
    private Emitter $source;

    private ReadableStream $stream;

    public function testRead(): void
    {
        $future = async(fn () => buffer($this->stream));

        $this->source->emit('Z');
        $this->source->emit('m9vLmJhcg=');
        $this->source->emit('=');
        $this->source->complete();

        self::assertSame('foo.bar', $future->await());
    }

    public function testInvalidDataMissingPadding(): void
    {
        $future = async(fn () => buffer($this->stream));

        $this->source->emit('Z');
        $this->source->emit('m9vLmJhcg=');
        $this->source->emit(''); // missing =
        $this->source->complete();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Failed to read stream chunk due to invalid base64 data');

        self::assertSame('foo.bar', $future->await());
    }

    public function testInvalidDataChar(): void
    {
        $future = async(fn () => buffer($this->stream));

        $this->source->emit('Z');
        $this->source->emit('!');
        $this->source->complete();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Failed to read stream chunk due to invalid base64 data');

        self::assertSame('foo.bar', $future->await());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = new Emitter;
        $this->stream = new Base64DecodingReadableStream(new IterableStream($this->source->pipe()));
    }
}
