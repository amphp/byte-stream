<?php

namespace Amp\ByteStream\Base64;

use Amp\ByteStream\WritableBuffer;
use Amp\PHPUnit\AsyncTestCase;

class Base64EncodingOutputStreamTest extends AsyncTestCase
{
    public function testWrite(): void
    {
        $buffer = new WritableBuffer;
        $stream = new Base64EncodingWritableStream($buffer);

        $stream->write('foo');
        $stream->write('.');
        $stream->write('bar');
        $stream->end();

        self::assertSame('Zm9vLmJhcg==', $buffer->buffer());
    }

    public function testEnd(): void
    {
        $buffer = new WritableBuffer;
        $stream = new Base64EncodingWritableStream($buffer);

        $stream->write('foo');
        $stream->write('.');
        $stream->write('');
        $stream->end('bar');

        self::assertSame('Zm9vLmJhcg==', $buffer->buffer());
    }
}
