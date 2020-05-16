<?php

namespace Amp\ByteStream\Test\Base64;

use Amp\ByteStream\Base64\Base64DecodingOutputStream;
use Amp\ByteStream\OutputBuffer;
use Amp\ByteStream\StreamException;
use Amp\PHPUnit\AsyncTestCase;

class Base64DecodingOutputStreamTest extends AsyncTestCase
{
    public function testWrite(): \Generator
    {
        $buffer = new OutputBuffer;
        $stream = new Base64DecodingOutputStream($buffer);

        yield $stream->write('Zm9');
        yield $stream->write('');
        yield $stream->write('vLmJhcg==');
        yield $stream->end();

        $this->assertSame('foo.bar', yield $buffer);
    }

    public function testEnd(): \Generator
    {
        $buffer = new OutputBuffer;
        $stream = new Base64DecodingOutputStream($buffer);

        yield $stream->write('Zm9');
        yield $stream->write('');
        yield $stream->end('vLmJhcg==');

        $this->assertSame('foo.bar', yield $buffer);
    }

    public function testInvalidDataMissingPadding(): \Generator
    {
        $buffer = new OutputBuffer;
        $stream = new Base64DecodingOutputStream($buffer);

        yield $stream->write('Zm9');
        yield $stream->write('');

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Invalid base64 near offset 3');

        yield $stream->end('vLmJhcg=');
    }

    public function testInvalidDataChar(): \Generator
    {
        $buffer = new OutputBuffer;
        $stream = new Base64DecodingOutputStream($buffer);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Invalid base64 near offset 0');

        yield $stream->write('Z!fsdf');
    }
}
