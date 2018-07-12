<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\StreamException;
use Amp\Emitter;
use Amp\PHPUnit\TestCase;
use Amp\PHPUnit\TestException;
use Concurrent\Task;

class IteratorStreamTest extends TestCase
{
    public function testReadIterator(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new IteratorStream($emitter->iterate());

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        $emitter->complete();

        $buffer = "";
        while (($chunk = $stream->read()) !== null) {
            $buffer .= $chunk;
        }

        $this->assertSame(\implode($values), $buffer);
        $this->assertNull($stream->read());
    }

    public function testFailingIterator(): void
    {
        $exception = new TestException;
        $value = "abc";

        $emitter = new Emitter;
        $stream = new IteratorStream($emitter->iterate());

        $emitter->emit($value);
        $emitter->fail($exception);

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

    public function testThrowsOnNonStringIteration(): void
    {
        $this->expectException(StreamException::class);

        $value = 42;

        $emitter = new Emitter;
        $stream = new IteratorStream($emitter->iterate());
        Task::async([$emitter, 'emit'], $value);

        $stream->read();
    }

    public function testFailsAfterException(): void
    {
        $this->expectException(StreamException::class);

        $emitter = new Emitter;
        $stream = new IteratorStream($emitter->iterate());
        $emitter->emit(42);

        try {
            $stream->read();
        } catch (StreamException $e) {
            $stream->read();
        }
    }
}
