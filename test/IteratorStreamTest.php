<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\StreamException;
use Amp\Emitter;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\PHPUnit\TestException;

class IteratorStreamTest extends TestCase
{
    public function testReadIterator()
    {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new IteratorStream($emitter->iterate());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();

            $buffer = "";
            while (($chunk = yield $stream->read()) !== null) {
                $buffer .= $chunk;
            }

            $this->assertSame(\implode($values), $buffer);
            $this->assertNull(yield $stream->read());
        });
    }

    public function testFailingIterator()
    {
        Loop::run(function () {
            $exception = new TestException;
            $value = "abc";

            $emitter = new Emitter;
            $stream = new IteratorStream($emitter->iterate());

            $emitter->emit($value);
            $emitter->fail($exception);

            $callable = $this->createCallback(1);

            try {
                while (($chunk = yield $stream->read()) !== null) {
                    $this->assertSame($value, $chunk);
                }

                $this->fail("No exception has been thrown");
            } catch (TestException $reason) {
                $this->assertSame($exception, $reason);
                $callable(); // <-- ensure this point is reached
            }
        });
    }

    public function testThrowsOnNonStringIteration()
    {
        $this->expectException(StreamException::class);
        Loop::run(function () {
            $value = 42;

            $emitter = new Emitter;
            $stream = new IteratorStream($emitter->iterate());

            $emitter->emit($value);

            yield $stream->read();
        });
    }

    public function testFailsAfterException()
    {
        $this->expectException(StreamException::class);
        Loop::run(function () {
            $value = 42;

            $emitter = new Emitter;
            $stream = new IteratorStream($emitter->iterate());

            $emitter->emit($value);

            try {
                yield $stream->read();
            } catch (StreamException $e) {
                yield $stream->read();
            }
        });
    }
}
