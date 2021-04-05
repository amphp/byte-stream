<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\Payload;
use Amp\ByteStream\PendingReadError;
use Amp\ByteStream\PipelineStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\PipelineSource;
use Revolt\EventLoop\Loop;
use function Revolt\Future\spawn;

class PayloadTest extends AsyncTestCase
{
    public function testBufferingAll()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        $emitter->complete();

        self::assertSame(\implode($values), $stream->buffer());
    }

    public function testFullStreamConsumption()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        Loop::delay(5, function () use ($emitter) {
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

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        foreach ($values as $value) {
            $emitter->emit($value);
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

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        $emitter->complete();

        self::assertSame(\implode($values), $stream->buffer());
    }

    public function testPartialStreamConsumption()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        $emitter->emit($values[0]);

        $chunk = $stream->read();

        self::assertSame(\array_shift($values), $chunk);

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        $emitter->complete();

        self::assertSame(\implode($values), $stream->buffer());
    }

    public function testFailingStream()
    {
        $exception = new TestException;
        $value = "abc";

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        $emitter->emit($value);
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

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        $readFuture = spawn(fn () => $stream->read());
        $emitter->error($exception);

        $callable = $this->createCallback(1);

        try {
            $readFuture->join();

            self::fail("No exception has been thrown");
        } catch (TestException $reason) {
            self::assertSame($exception, $reason);
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testEmptyStream()
    {
        $emitter = new PipelineSource;
        $emitter->complete();
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        self::assertNull($stream->read());
    }

    public function testEmptyStringStream()
    {
        $value = "";

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        $emitter->emit($value);

        $emitter->complete();

        self::assertSame("", $stream->buffer());
    }

    public function testReadAfterCompletion()
    {
        $value = "abc";

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        $emitter->emit($value);
        $emitter->complete();

        self::assertSame($value, $stream->read());
        self::assertNull($stream->read());
    }

    public function testPendingRead()
    {
        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        Loop::delay(0, function () use ($emitter) {
            $emitter->emit("test");
        });

        self::assertSame("test", $stream->read());
    }

    public function testPendingReadError()
    {
        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));
        spawn(fn () => $stream->read());

        $this->expectException(PendingReadError::class);

        spawn(fn () => $stream->read())->join();
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
}
