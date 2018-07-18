<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\Message;
use Amp\ByteStream\PendingReadError;
use Amp\ByteStream\StreamException;
use Amp\Emitter;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\PHPUnit\TestException;
use Concurrent\Task;

class MessageTest extends TestCase
{
    public function testBufferingAll(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->extractIterator()));

        Task::async(function () use ($emitter, $values) {
            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();
        });

        $this->assertSame(\implode($values), $stream->buffer());
    }

    public function testFullStreamConsumption(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->extractIterator()));

        Task::async(function () use ($emitter, $values) {
            foreach ($values as $value) {
                $emitter->emit($value);
            }

            Loop::delay(5, function () use ($emitter) {
                $emitter->complete();
            });
        });

        $buffer = "";
        while (($chunk = $stream->read()) !== null) {
            $buffer .= $chunk;
        }

        $this->assertSame(\implode($values), $buffer);
        $this->assertSame("", $stream->buffer());
    }

    public function testFastResolvingStream(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->extractIterator()));

        Task::async(function () use ($emitter, $values) {
            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();
        });

        $emitted = [];
        while (($chunk = $stream->read()) !== null) {
            $emitted[] = $chunk;
        }

        $this->assertSame($values, $emitted);
        $this->assertSame("", $stream->buffer());
    }

    public function testFastResolvingStreamBufferingOnly(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->extractIterator()));

        Task::async(function () use ($emitter, $values) {
            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();
        });

        $this->assertSame(\implode($values), $stream->buffer());
    }

    public function testPartialStreamConsumption(): void
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->extractIterator()));

        Task::async([$emitter, 'emit'], $values[0]);

        $chunk = $stream->read();

        $this->assertSame(\array_shift($values), $chunk);

        foreach ($values as $value) {
            Task::async([$emitter, 'emit'], $value);
        }

        Task::async([$emitter, 'complete']);

        $this->assertSame(\implode($values), $stream->buffer());
    }

    public function testFailingStream(): void
    {
        $exception = new TestException;
        $value = "abc";

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->extractIterator()));

        Task::async([$emitter, 'emit'], $value);
        Task::async([$emitter, 'fail'], $exception);

        $callable = $this->createCallback(1);

        try {
            while (($chunk = $stream->read()) !== null) {
                $this->assertSame($value, $chunk);
            }

            $this->fail("No exception has been thrown");
        } catch (StreamException $reason) {
            $this->assertSame($exception, $reason->getPrevious());
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testFailingStreamWithPendingRead(): void
    {
        $exception = new TestException;

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->extractIterator()));

        $readOp = Task::async([$stream, 'read']);
        $emitter->fail($exception);

        $callable = $this->createCallback(1);

        try {
            Task::await($readOp);

            $this->fail("No exception has been thrown");
        } catch (StreamException $reason) {
            $this->assertSame($exception, $reason->getPrevious());
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testEmptyStream(): void
    {
        $emitter = new Emitter;
        $emitter->complete();
        $stream = new Message(new IteratorStream($emitter->extractIterator()));

        $this->assertNull($stream->read());
    }

    public function testEmptyStringStream(): void
    {
        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->extractIterator()));

        Task::async(function () use ($emitter) {
            $emitter->emit("");
            $emitter->complete();
        });

        $this->assertSame("", $stream->buffer());
    }

    public function testReadAfterCompletion(): void
    {
        $value = "abc";

        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->extractIterator()));

        Task::async(function () use ($emitter, $value) {
            $emitter->emit($value);
            $emitter->complete();
        });

        $this->assertSame($value, $stream->read());
        $this->assertNull($stream->read());
    }

    public function testGetInputStream(): void
    {
        $inputStream = new InMemoryStream("");
        $message = new Message($inputStream);

        $this->assertSame($inputStream, $message->getInputStream());
        $this->assertSame("", $message->getInputStream()->read());
    }

    public function testPendingRead(): void
    {
        $emitter = new Emitter;
        $stream = new Message(new IteratorStream($emitter->extractIterator()));

        Loop::delay(0, function () use ($emitter) {
            Task::async(function () use ($emitter) {
                $emitter->emit("test");
            });
        });

        $this->assertSame("test", $stream->read());
    }

    public function testPendingReadError(): void
    {
        try {
            $emitter = new Emitter;
            $stream = new Message(new IteratorStream($emitter->extractIterator()));
            $readOp = Task::async([$stream, 'read']);

            $this->expectException(PendingReadError::class);
            $stream->read();
        } catch (\Throwable $e) {
            Task::await($readOp);

            throw $e;
        }
    }
}
