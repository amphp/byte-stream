<?php

namespace Amp\ByteStream\Base64;

use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\ReadableStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline\Queue;
use function Amp\async;
use function Amp\ByteStream\buffer;

final class Base64EncodingInputStreamTest extends AsyncTestCase
{
    private Queue $source;

    private ReadableStream $stream;

    public function testRead(): void
    {
        $future = async(fn () => buffer($this->stream));

        $this->source->pushAsync('f');
        $this->source->pushAsync('o');
        $this->source->pushAsync('o');
        $this->source->pushAsync('.');
        $this->source->pushAsync('b');
        $this->source->pushAsync('a');
        $this->source->pushAsync('r');
        $this->source->complete();

        self::assertSame('Zm9vLmJhcg==', $future->await());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = new Queue;
        $this->stream = new Base64EncodingReadableStream(new ReadableIterableStream($this->source->pipe()));
    }
}
