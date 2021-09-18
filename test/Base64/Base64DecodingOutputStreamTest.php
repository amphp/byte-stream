<?php

namespace Amp\ByteStream\Test\Base64;

use Amp\ByteStream\Base64\Base64DecodingOutputStream;
use Amp\ByteStream\OutputBuffer;
use Amp\ByteStream\StreamException;
use Amp\PHPUnit\AsyncTestCase;

class Base64DecodingOutputStreamTest extends AsyncTestCase
{
    public function testWrite(): void
    {
        $buffer = new OutputBuffer;
        $stream = new Base64DecodingOutputStream($buffer);

        $stream->write('Zm9')->join();
        $stream->write('')->join();
        $stream->write('vLmJhcg==')->join();
        $stream->end()->join();

        self::assertSame('foo.bar', $buffer->buffer());
    }

    public function testEnd(): void
    {
        $buffer = new OutputBuffer;
        $stream = new Base64DecodingOutputStream($buffer);

        $stream->write('Zm9')->join();
        $stream->write('')->join();
        $stream->end('vLmJhcg==')->join();

        self::assertSame('foo.bar', $buffer->buffer());
    }

    public function testInvalidDataMissingPadding(): void
    {
        $buffer = new OutputBuffer;
        $stream = new Base64DecodingOutputStream($buffer);

        $stream->write('Zm9')->join();
        $stream->write('')->join();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Invalid base64 near offset 3');

        $stream->end('vLmJhcg=')->join();
    }

    public function testInvalidDataChar(): void
    {
        $buffer = new OutputBuffer;
        $stream = new Base64DecodingOutputStream($buffer);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Invalid base64 near offset 0');

        $stream->write('Z!fsdf')->join();
    }
}
