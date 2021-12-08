<?php

namespace Amp\ByteStream\Base64;

use Amp\ByteStream\StreamException;
use Amp\ByteStream\WriteBuffer;
use Amp\PHPUnit\AsyncTestCase;

class Base64DecodingOutputStreamTest extends AsyncTestCase
{
    public function testWrite(): void
    {
        $buffer = new WriteBuffer;
        $stream = new Base64DecodingWritableStream($buffer);

        $stream->write('Zm9');
        $stream->write('');
        $stream->write('vLmJhcg==');
        $stream->end();

        self::assertSame('foo.bar', $buffer->buffer());
    }

    public function testEnd(): void
    {
        $buffer = new WriteBuffer;
        $stream = new Base64DecodingWritableStream($buffer);

        $stream->write('Zm9');
        $stream->write('');
        $stream->end('vLmJhcg==');

        self::assertSame('foo.bar', $buffer->buffer());
    }

    public function testInvalidDataMissingPadding(): void
    {
        $buffer = new WriteBuffer;
        $stream = new Base64DecodingWritableStream($buffer);

        $stream->write('Zm9');
        $stream->write('');

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Invalid base64 near offset 3');

        $stream->end('vLmJhcg=');
    }

    public function testInvalidDataChar(): void
    {
        $buffer = new WriteBuffer;
        $stream = new Base64DecodingWritableStream($buffer);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Invalid base64 near offset 0');

        $stream->write('Z!fsdf');
    }
}
