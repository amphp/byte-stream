<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\Message;
use Amp\Emitter;
use Amp\Loop;
use Amp\PHPUnit\TestCase;

class MessageTest extends TestCase {
    public function testBufferingAll() {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message($emitter->iterate());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();

            $this->assertSame(\implode($values), yield $stream);
        });
    }

    public function testFullStreamConsumption() {
        Loop::run(function () use (&$invoked) {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message($emitter->iterate());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            Loop::delay(5, function () use ($emitter) {
                $emitter->complete();
            });

            $buffer = "";
            while (($chunk = yield $stream->read()) !== null) {
                $buffer .= $chunk;
            }

            $this->assertSame(\implode($values), $buffer);
            $this->assertSame("", yield $stream);
        });
    }

    public function testFastResolvingStream() {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message($emitter->iterate());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();

            $emitted = [];
            while (($chunk = yield $stream->read()) !== null) {
                $emitted[] = $chunk;
            }

            $this->assertSame($values, $emitted);
            $this->assertSame("", yield $stream);
        });
    }

    public function testFastResolvingStreamBufferingOnly() {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message($emitter->iterate());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();

            $this->assertSame(\implode($values), yield $stream);
        });
    }

    public function testPartialStreamConsumption() {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $stream = new Message($emitter->iterate());

            $emitter->emit($values[0]);

            $chunk = yield $stream->read();

            $this->assertSame(\array_shift($values), $chunk);

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->complete();

            $this->assertSame(\implode($values), yield $stream);
        });
    }

    public function testFailingStream() {
        Loop::run(function () {
            $exception = new \Exception;
            $value = "abc";

            $emitter = new Emitter;
            $stream = new Message($emitter->iterate());

            $emitter->emit($value);
            $emitter->fail($exception);

            try {
                while (($chunk = yield $stream->read()) !== null) {
                    $this->assertSame($value, $chunk);
                }
            } catch (\Exception $reason) {
                $this->assertSame($exception, $reason);
            }
        });
    }

    public function testEmptyStream() {
        Loop::run(function () {
            $emitter = new Emitter;
            $emitter->complete();
            $stream = new Message($emitter->iterate());

            $this->assertNull(yield $stream->read());
        });
    }

    public function testReadAfterCompletion() {
        Loop::run(function () {
            $value = "abc";

            $emitter = new Emitter;
            $stream = new Message($emitter->iterate());

            $emitter->emit($value);
            $emitter->complete();

            $this->assertSame($value, yield $stream->read());
            $this->assertNull(yield $stream->read());
        });
    }
}
