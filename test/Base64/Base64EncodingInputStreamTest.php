<?php

namespace Amp\ByteStream\Test\Base64;

use Amp\ByteStream\Base64\Base64EncodingInputStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\PipelineStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline\Subject;
use function Amp\coroutine;
use function Amp\ByteStream\buffer;

class Base64EncodingInputStreamTest extends AsyncTestCase
{
    private Subject $source;

    private InputStream $stream;

    public function testRead(): void
    {
        $future = coroutine(fn () => buffer($this->stream));

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

        $this->source = new Subject;
        $this->stream = new Base64EncodingInputStream(new PipelineStream($this->source->asPipeline()));
    }
}
