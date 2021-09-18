<?php

namespace Amp\ByteStream\Test\Base64;

use Amp\ByteStream\Base64\Base64EncodingOutputStream;
use Amp\ByteStream\OutputBuffer;
use Amp\PHPUnit\AsyncTestCase;

class Base64EncodingOutputStreamTest extends AsyncTestCase
{
    public function testWrite(): void
    {
        $buffer = new OutputBuffer;
        $stream = new Base64EncodingOutputStream($buffer);

        $stream->write('foo')->join();
        $stream->write('.')->join();
        $stream->write('bar')->join();
        $stream->end()->join();

        self::assertSame('Zm9vLmJhcg==', $buffer->buffer());
    }

    public function testEnd(): void
    {
        $buffer = new OutputBuffer;
        $stream = new Base64EncodingOutputStream($buffer);

        $stream->write('foo')->join();
        $stream->write('.')->join();
        $stream->write('')->join();
        $stream->end('bar')->join();

        self::assertSame('Zm9vLmJhcg==', $buffer->buffer());
    }
}
