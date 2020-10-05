<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\PipelineStream;
use Amp\ByteStream\StreamException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\PipelineSource;

class PipelineStreamTest extends AsyncTestCase
{
    public function testReadIterator()
    {
        $values = ["abc", "def", "ghi"];

        $source = new PipelineSource;
        $stream = new PipelineStream($source->pipe());

        foreach ($values as $value) {
            $source->emit($value);
        }

        $source->complete();

        $buffer = "";
        while (($chunk = $stream->read()) !== null) {
            $buffer .= $chunk;
        }

        $this->assertSame(\implode($values), $buffer);
        $this->assertNull($stream->read());
    }

    public function testFailingIterator()
    {
        $exception = new TestException;
        $value = "abc";

        $source = new PipelineSource;
        $stream = new PipelineStream($source->pipe());

        $source->emit($value);
        $source->fail($exception);

        $callable = $this->createCallback(1);

        try {
            while (($chunk = $stream->read()) !== null) {
                $this->assertSame($value, $chunk);
            }

            $this->fail("No exception has been thrown");
        } catch (TestException $reason) {
            $this->assertSame($exception, $reason);
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testThrowsOnNonStringIteration()
    {
        $this->expectException(StreamException::class);

        $value = 42;

        $source = new PipelineSource;
        $stream = new PipelineStream($source->pipe());

        $source->emit($value);

        $stream->read();
    }

    public function testFailsAfterException()
    {
        $this->expectException(StreamException::class);

        $value = 42;

        $source = new PipelineSource;
        $stream = new PipelineStream($source->pipe());

        $source->emit($value);

        try {
            $stream->read();
        } catch (StreamException $e) {
            $stream->read();
        }
    }
}
