<?php

namespace Amp\ByteStream\Test\Base64;

use Amp\ByteStream\Base64\Base64EncodingOutputStream;
use Amp\ByteStream\OutputBuffer;
use Amp\PHPUnit\AsyncTestCase;

class Base64EncodingOutputStreamTest extends AsyncTestCase
{
    public function testWrite(): \Generator
    {
        $buffer = new OutputBuffer;
        $stream = new Base64EncodingOutputStream($buffer);

        yield $stream->write('foo');
        yield $stream->write('.');
        yield $stream->write('bar');
        yield $stream->end();

        $this->assertSame('Zm9vLmJhcg==', yield $buffer);
    }

    public function testEnd(): \Generator
    {
        $buffer = new OutputBuffer;
        $stream = new Base64EncodingOutputStream($buffer);

        yield $stream->write('foo');
        yield $stream->write('.');
        yield $stream->write('');
        yield $stream->end('bar');

        $this->assertSame('Zm9vLmJhcg==', yield $buffer);
    }
}
