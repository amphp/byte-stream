<?php

namespace Amp\ByteStream\Test\Base64;

use Amp\ByteStream\Base64\Base64EncodingReadableStream;
use Amp\ByteStream\IterableStream;
use Amp\ByteStream\ReadableStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline\Emitter;
use function Amp\async;
use function Amp\ByteStream\buffer;

class Base64EncodingInputStreamTest extends AsyncTestCase
{
    private Emitter $source;

    private ReadableStream $stream;

    public function testRead(): void
    {
        $future = async(fn () => buffer($this->stream));

        $this->source->emit('f');
        $this->source->emit('o');
        $this->source->emit('o');
        $this->source->emit('.');
        $this->source->emit('b');
        $this->source->emit('a');
        $this->source->emit('r');
        $this->source->complete();

        self::assertSame('Zm9vLmJhcg==', $future->await());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = new Emitter;
        $this->stream = new Base64EncodingReadableStream(new IterableStream($this->source->pipe()));
    }
}
