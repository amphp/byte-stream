<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\OutputBuffer;
use Amp\PHPUnit\AsyncTestCase;

class OutputBufferTest extends AsyncTestCase
{
    public function testWrite()
    {
        $output = new OutputBuffer();
        $output->write('foo');
        $output->end();

        $this->assertSame('foo', yield $output);
    }

    public function testEnd()
    {
        $output = new OutputBuffer();
        $output->write('foo');
        $output->end('bar');

        $this->assertSame('foobar', yield $output);
    }

    public function testThrowsOnWritingToClosedBuffer()
    {
        $this->expectException(ClosedException::class);

        $output = new OutputBuffer();
        $output->end('foo');
        $output->write('bar');
    }

    public function testThrowsOnEndingToClosedBuffer()
    {
        $this->expectException(ClosedException::class);

        $output = new OutputBuffer();
        $output->end('foo');
        $output->end('bar');
    }
}
