<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\Payload;
use Amp\ByteStream\PendingReadError;
use Amp\ByteStream\PipelineStream;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\PipelineSource;
use function Amp\async;
use function Amp\await;

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

        $this->assertSame(\implode($values), $stream->buffer());
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

        $this->assertSame(\implode($values), $buffer);
        $this->assertSame("", $stream->buffer());
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

        $this->assertSame($values, $emitted);
        $this->assertSame("", $stream->buffer());
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

        $this->assertSame(\implode($values), $stream->buffer());
    }

    public function testPartialStreamConsumption()
    {
        $values = ["abc", "def", "ghi"];

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        $emitter->emit($values[0]);

        $chunk = $stream->read();

        $this->assertSame(\array_shift($values), $chunk);

        foreach ($values as $value) {
            $emitter->emit($value);
        }

        $emitter->complete();

        $this->assertSame(\implode($values), $stream->buffer());
    }

    public function testFailingStream()
    {
        $exception = new TestException;
        $value = "abc";

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

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

    public function testFailingStreamWithPendingRead()
    {
        $exception = new TestException;
        $value = "abc";

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        $readPromise = async(fn() => $stream->read());
        $emitter->fail($exception);

        $callable = $this->createCallback(1);

        try {
            await($readPromise);

            $this->fail("No exception has been thrown");
        } catch (TestException $reason) {
            $this->assertSame($exception, $reason);
            $callable(); // <-- ensure this point is reached
        }
    }

    public function testEmptyStream()
    {
        $emitter = new PipelineSource;
        $emitter->complete();
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        $this->assertNull($stream->read());
    }

    public function testEmptyStringStream()
    {
        $value = "";

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        $emitter->emit($value);

        $emitter->complete();

        $this->assertSame("", $stream->buffer());
    }

    public function testReadAfterCompletion()
    {
        $value = "abc";

        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        $emitter->emit($value);
        $emitter->complete();

        $this->assertSame($value, $stream->read());
        $this->assertNull($stream->read());
    }

    public function testPendingRead()
    {
        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));

        Loop::delay(0, function () use ($emitter) {
            $emitter->emit("test");
        });

        $this->assertSame("test", $stream->read());
    }

    public function testPendingReadError()
    {
        $emitter = new PipelineSource;
        $stream = new Payload(new PipelineStream($emitter->pipe()));
        async(fn () => $stream->read());

        $this->expectException(PendingReadError::class);

        await(async(fn () => $stream->read()));
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
        $this->assertSame($data, $stream->buffer());
        $this->assertSame($data, $stream->buffer());
    }
}
