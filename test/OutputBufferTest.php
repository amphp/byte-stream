<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\OutputBuffer;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\await;

class OutputBufferTest extends AsyncTestCase
{
    public function testWrite()
    {
        $output = new OutputBuffer();
        $output->write('foo');
        $output->end();

        $this->assertSame('foo', await($output));
    }

    public function testEnd()
    {
        $output = new OutputBuffer();
        $output->write('foo');
        $output->end('bar');

        $this->assertSame('foobar', await($output));
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
