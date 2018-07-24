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
        $stream = new IteratorStream($emitter->extractIterator());

        foreach ($values as $value) {
            Task::async(function () use ($emitter, $value) {
                $emitter->emit($value);
            });
        }

        Task::async(function () use ($emitter) {
            $emitter->complete();
        });

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
        $stream = new IteratorStream($emitter->extractIterator());

        Task::async(function () use ($emitter, $value) {
            $emitter->emit($value);
        });

        Task::async(function () use ($emitter, $exception) {
            $emitter->fail($exception);
        });

        $callable = $this->createCallback(1);

        try {
            while (null !== $chunk = $stream->read()) {
                $this->assertSame($value, $chunk);
            }

            $this->fail("No exception has been thrown");
        } catch (StreamException $reason) {
            $this->assertSame($exception, $reason->getPrevious());
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testThrowsOnNonStringIteration(): void
    {
        $this->expectException(StreamException::class);

        $value = 42;

        $emitter = new Emitter;
        $stream = new IteratorStream($emitter->extractIterator());
        Task::async([$emitter, 'emit'], $value);

        $stream->read();
    }

    public function testFailsAfterException(): void
    {
        $this->expectException(StreamException::class);

        $emitter = new Emitter;
        $stream = new IteratorStream($emitter->extractIterator());
        Task::async(function () use ($emitter) {
            $emitter->emit(42);
        });

        try {
            $stream->read();
        } catch (StreamException $e) {
            $stream->read();
        }
    }
}
