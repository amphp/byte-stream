<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream\Test;

use Amp\ByteStream\IteratorStream;
use Amp\Emitter;
use Concurrent\AsyncTestCase;
use Concurrent\Stream\StreamClosedException;
use Concurrent\Stream\StreamException;
use Concurrent\Task;

class IteratorStreamTest extends AsyncTestCase
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
        while (($chunk = $stream->read(1)) !== null) {
            $buffer .= $chunk;
        }

        $this->assertSame(\implode($values), $buffer);
        $this->assertNull($stream->read(1));
    }

    public function testFailingIterator(): void
    {
        $exception = new \RuntimeException();
        $value = "abc";

        $emitter = new Emitter;
        $stream = new IteratorStream($emitter->extractIterator());

        Task::async(function () use ($emitter, $value) {
            $emitter->emit($value);
        });

        Task::async(function () use ($emitter, $exception) {
            $emitter->fail($exception);
        });

        $this->expectExceptionObject($exception);

        try {
            while (null !== $chunk = $stream->read(3)) {
                $this->assertSame($value, $chunk);
            }

            $this->fail("No exception has been thrown");
        } catch (StreamClosedException $reason) {
            throw $reason->getPrevious();
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
