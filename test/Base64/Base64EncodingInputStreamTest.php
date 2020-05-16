<?php

namespace Amp\ByteStream\Test\Base64;

use Amp\ByteStream\Base64\Base64EncodingInputStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\Emitter;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\ByteStream\buffer;

class Base64EncodingInputStreamTest extends AsyncTestCase
{
    /** @var Emitter */
    private $emitter;

    /** @var InputStream */
    private $stream;

    public function testRead(): \Generator
    {
        $promise = buffer($this->stream);

        $this->emitter->emit('f');
        $this->emitter->emit('o');
        $this->emitter->emit('o');
        $this->emitter->emit('.');
        $this->emitter->emit('b');
        $this->emitter->emit('a');
        $this->emitter->emit('r');
        $this->emitter->complete();

        $this->assertSame('Zm9vLmJhcg==', yield $promise);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->emitter = new Emitter;
        $this->stream = new Base64EncodingInputStream(new IteratorStream($this->emitter->iterate()));
    }
}
