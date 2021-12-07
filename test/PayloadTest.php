<?php

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Pipeline\Emitter;
use Revolt\EventLoop;
use function Amp\async;

class PayloadTest extends AsyncTestCase
{
    public function testBufferingAll()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        foreach ($values as $value) {
            $emitter->emit($value)->ignore();
        }

        $emitter->complete();

        self::assertSame(\implode($values), $stream->buffer());
    }

    public function testFullStreamConsumption()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        foreach ($values as $value) {
            $emitter->emit($value)->ignore();
        }

        EventLoop::delay(0.005, function () use ($emitter) {
            $emitter->complete();
        });

        $buffer = "";
        while (($chunk = $stream->read()) !== null) {
            $buffer .= $chunk;
        }

        self::assertSame(\implode($values), $buffer);
        self::assertSame("", $stream->buffer());
    }

    public function testFastResolvingStream()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        foreach ($values as $value) {
            $emitter->emit($value)->ignore();
        }

        $emitter->complete();

        $emitted = [];
        while (($chunk = $stream->read()) !== null) {
            $emitted[] = $chunk;
        }

        self::assertSame($values, $emitted);
        self::assertSame("", $stream->buffer());
    }

    public function testFastResolvingStreamBufferingOnly()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        foreach ($values as $value) {
            $emitter->emit($value)->ignore();
        }

        $emitter->complete();

        self::assertSame(\implode($values), $stream->buffer());
    }

    public function testPartialStreamConsumption()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        $emitter->emit($values[0])->ignore();

        $chunk = $stream->read();

        self::assertSame(\array_shift($values), $chunk);

        foreach ($values as $value) {
            $emitter->emit($value)->ignore();
        }

        $emitter->complete();

        self::assertSame(\implode($values), $stream->buffer());
    }

    public function testFailingStream()
    {
        $exception = new TestException;
        $value = "abc";

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        $emitter->emit($value)->ignore();
        $emitter->error($exception);

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

    public function testFailingStreamWithPendingRead()
    {
        $exception = new TestException;
        $value = "abc";

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        $readFuture = async(fn () => $stream->read());
        $emitter->error($exception);

        $callable = $this->createCallback(1);

        try {
            $readFuture->await();

            self::fail("No exception has been thrown");
        } catch (TestException $reason) {
            self::assertSame($exception, $reason);
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testEmptyStream()
    {
        $emitter = new Emitter;
        $emitter->complete();
        $stream = new Payload(new IterableStream($emitter->pipe()));

        self::assertNull($stream->read());
    }

    public function testEmptyStringStream()
    {
        $value = "";

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        $emitter->emit($value)->ignore();

        $emitter->complete();

        self::assertSame("", $stream->buffer());
    }

    public function testReadAfterCompletion()
    {
        $value = "abc";

        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        $emitter->emit($value)->ignore();
        $emitter->complete();

        self::assertSame($value, $stream->read());
        self::assertNull($stream->read());
    }

    public function testPendingRead()
    {
        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));

        EventLoop::delay(0, function () use ($emitter) {
            $emitter->emit("test")->ignore();
        });

        self::assertSame("test", $stream->read());
    }

    public function testPendingReadError()
    {
        $emitter = new Emitter;
        $stream = new Payload(new IterableStream($emitter->pipe()));
        async(fn () => $stream->read());

        $this->expectException(PendingReadError::class);

        try {
            async(fn () => $stream->read())->await();
        } finally {
            $emitter->complete();
        }
    }

    public function testReadAfterBuffer()
    {
        $stream = new Payload(new InMemoryStream("test"));
        $stream->buffer();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Cannot stream message data once a buffered message has been requested");

        $stream->read();
    }

    public function testFurtherCallsToBufferReturnSameData()
    {
        $data = "test";
        $stream = new Payload(new InMemoryStream($data));
        self::assertSame($data, $stream->buffer());
        self::assertSame($data, $stream->buffer());
    }

    public function testStringAsStream()
    {
        $data = "test";
        $stream = new Payload($data);
        self::assertSame($data, $stream->read());
        self::assertNull($stream->read());
        self::assertSame('', $stream->buffer());

        $stream = new Payload($data);
        self::assertSame($data, $stream->buffer());
        self::assertSame($data, $stream->buffer());
    }
}
