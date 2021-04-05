<?php

namespace Amp\ByteStream\Test\Base64;

use Amp\ByteStream\Base64\Base64DecodingInputStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\PipelineStream;
use Amp\ByteStream\StreamException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PipelineSource;
use function Amp\ByteStream\buffer;
use function Revolt\Future\spawn;

class Base64DecodingInputStreamTest extends AsyncTestCase
{
    private PipelineSource $source;

    private InputStream $stream;

    public function testRead(): void
    {
        $future = spawn(fn () => buffer($this->stream));

        $this->source->emit('Z');
        $this->source->emit('m9vLmJhcg=');
        $this->source->emit('=');
        $this->source->complete();

        self::assertSame('foo.bar', $future->join());
    }

    public function testInvalidDataMissingPadding(): void
    {
        $future = spawn(fn () => buffer($this->stream));

        $this->source->emit('Z');
        $this->source->emit('m9vLmJhcg=');
        $this->source->emit(''); // missing =
        $this->source->complete();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Failed to read stream chunk due to invalid base64 data');

        self::assertSame('foo.bar', $future->join());
    }

    public function testInvalidDataChar(): void
    {
        $future = spawn(fn () => buffer($this->stream));

        $this->source->emit('Z');
        $this->source->emit('!');
        $this->source->complete();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Failed to read stream chunk due to invalid base64 data');

        self::assertSame('foo.bar', $future->join());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = new PipelineSource;
        $this->stream = new Base64DecodingInputStream(new PipelineStream($this->source->pipe()));
    }
}
