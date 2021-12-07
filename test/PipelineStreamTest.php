<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\PipelineStream;
use Amp\ByteStream\StreamException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Pipeline\Emitter;

class PipelineStreamTest extends AsyncTestCase
{
    public function testReadIterator(): void
    {
        $values = ["abc", "def", "ghi"];

        $source = new Emitter;
        $stream = new PipelineStream($source->pipe());

        foreach ($values as $value) {
            $source->emit($value);
        }

        $source->complete();

        $buffer = "";
        while (($chunk = $stream->read()) !== null) {
            $buffer .= $chunk;
        }

        self::assertSame(\implode($values), $buffer);
        self::assertNull($stream->read());
    }

    public function testFailingIterator(): void
    {
        $exception = new TestException;
        $value = "abc";

        $source = new Emitter;
        $stream = new PipelineStream($source->pipe());

        $source->emit($value);
        $source->error($exception);

        $callable = $this->createCallback(1);

        try {
            while (($chunk = $stream->read()) !== null) {
                self::assertSame($value, $chunk);
            }

            self::fail("No exception has been thrown");
        } catch (TestException $reason) {
            self::assertSame($exception, $reason);
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testThrowsOnNonStringIteration(): void
    {
        $this->expectException(StreamException::class);

        $value = 42;

        $source = new Emitter;
        $stream = new PipelineStream($source->pipe());

        $source->emit($value);

        $stream->read();
    }

    public function testFailsAfterException(): void
    {
        $this->expectException(StreamException::class);

        $value = 42;

        $source = new Emitter;
        $stream = new PipelineStream($source->pipe());

        $source->emit($value);

        try {
            $stream->read();
        } catch (StreamException $e) {
            $stream->read();
        }
    }
}
