<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\Base64DecodingInputStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\StreamException;
use Amp\Emitter;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\ByteStream\buffer;

class Base64DecodingInputStreamTest extends AsyncTestCase
{
    /** @var Emitter */
    private $emitter;

    /** @var InputStream */
    private $stream;

    public function testRead(): \Generator
    {
        $promise = buffer($this->stream);

        $this->emitter->emit('Z');
        $this->emitter->emit('m9vLmJhcg=');
        $this->emitter->emit('=');
        $this->emitter->complete();

        $this->assertSame('foo.bar', yield $promise);
    }

    public function testInvalidDataMissingPadding(): \Generator
    {
        $promise = buffer($this->stream);

        $this->emitter->emit('Z');
        $this->emitter->emit('m9vLmJhcg=');
        $this->emitter->emit(''); // missing =
        $this->emitter->complete();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Failed to read stream chunk due to invalid base64 data');

        $this->assertSame('foo.bar', yield $promise);
    }

    public function testInvalidDataChar(): \Generator
    {
        $promise = buffer($this->stream);

        $this->emitter->emit('Z');
        $this->emitter->emit('!');
        $this->emitter->complete();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Failed to read stream chunk due to invalid base64 data');

        $this->assertSame('foo.bar', yield $promise);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->emitter = new Emitter;
        $this->stream = new Base64DecodingInputStream(new IteratorStream($this->emitter->iterate()));
    }
}
