<?php

namespace Amp\ByteStream\Test\Base64;

use Amp\ByteStream\Base64\Base64EncodingOutputStream;
use Amp\ByteStream\OutputBuffer;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\await;

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

        $this->assertSame('Zm9vLmJhcg==', await($buffer));
    }

    public function testEnd(): void
    {
        $buffer = new OutputBuffer;
        $stream = new Base64EncodingOutputStream($buffer);

        $stream->write('foo');
        $stream->write('.');
        $stream->write('');
        $stream->end('bar');

        $this->assertSame('Zm9vLmJhcg==', await($buffer));
    }
}
