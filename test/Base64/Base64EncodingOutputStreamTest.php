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

        $stream->write('foo');
        $stream->write('.');
        $stream->write('bar');
        $stream->end();

        self::assertSame('Zm9vLmJhcg==', $buffer->buffer());
    }

    public function testEnd(): void
    {
        $buffer = new OutputBuffer;
        $stream = new Base64EncodingOutputStream($buffer);

        $stream->write('foo');
        $stream->write('.');
        $stream->write('');
        $stream->end('bar');

        self::assertSame('Zm9vLmJhcg==', $buffer->buffer());
    }
}
