<?php

namespace Amp\ByteStream\Base64;

use Amp\ByteStream\StreamException;
use Amp\ByteStream\WritableBuffer;
use Amp\PHPUnit\AsyncTestCase;

final class Base64DecodingOutputStreamTest extends AsyncTestCase
{
    public function testWrite(): void
    {
        $buffer = new WritableBuffer;
        $stream = new Base64DecodingWritableStream($buffer);

        $stream->write('Zm9');
        $stream->write('');
        $stream->write('vLmJhcg==');
        $stream->end();

        self::assertSame('foo.bar', $buffer->buffer());
    }

    public function testEnd(): void
    {
        $buffer = new WritableBuffer;
        $stream = new Base64DecodingWritableStream($buffer);

        $stream->write('Zm9');
        $stream->write('');
        $stream->write('vLmJhcg==');
        $stream->end();

        self::assertSame('foo.bar', $buffer->buffer());
    }

    public function testInvalidDataMissingPadding(): void
    {
        $buffer = new WritableBuffer;
        $stream = new Base64DecodingWritableStream($buffer);

        $stream->write('Zm9');
        $stream->write('');
        $stream->write('vLmJhcg=');

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Invalid base64 near offset 11');

        $stream->end();
    }

    public function testInvalidDataChar(): void
    {
        $buffer = new WritableBuffer;
        $stream = new Base64DecodingWritableStream($buffer);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Invalid base64 near offset 0');

        $stream->write('Z!fsdf');
    }
}
