<?php

namespace Amp\ByteStream\Test\Base64;

use Amp\ByteStream\Base64\Base64EncodingInputStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\PipelineStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PipelineSource;
use function Amp\async;
use function Amp\await;
use function Amp\ByteStream\buffer;

class Base64EncodingInputStreamTest extends AsyncTestCase
{
    private PipelineSource $source;

    private InputStream $stream;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = new PipelineSource;
        $this->stream = new Base64EncodingInputStream(new PipelineStream($this->source->pipe()));
    }


    public function testRead(): void
    {
        $promise = async(fn() => buffer($this->stream));

        $this->source->emit('f');
        $this->source->emit('o');
        $this->source->emit('o');
        $this->source->emit('.');
        $this->source->emit('b');
        $this->source->emit('a');
        $this->source->emit('r');
        $this->source->complete();

        $this->assertSame('Zm9vLmJhcg==', await($promise));
    }
}
