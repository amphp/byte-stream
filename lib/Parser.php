<?php

namespace Amp\ByteStream;

use Amp\Failure;
use Amp\InvalidYieldError;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;

class Parser implements WritableStream {
    const CHUNK_SIZE = 8192;

    /** @var \Generator */
    private $generator;

    /** @var string */
    private $buffer = '';

    /** @var int|string|null */
    private $delimiter;

    /**
     * @param \Generator $generator
     *
     * @throws \Amp\InvalidYieldError If the generator yields an invalid value.
     */
    public function __construct(\Generator $generator) {
        $this->generator = $generator;

        $this->delimiter = $this->generator->current();

        if (!$this->generator->valid()) {
            $this->dispose();
            return;
        }

        if ($this->delimiter !== null
            && (!\is_int($this->delimiter) || $this->delimiter <= 0)
            && (!\is_string($this->delimiter) || !\strlen($this->delimiter))
        ) {
            throw new InvalidYieldError(
                $generator,
                \sprintf(
                    "Unexpected yield; Expected NULL, an int greater than 0, or a non-empty string; %s given",
                    \is_object($this->delimiter) ? \sprintf("instance of %s", \get_class($this->delimiter)) : \gettype($this->delimiter)
                )
            );
        }
    }

    /**
     * Cancels the generator parser and returns any remaining data in the internal buffer. Writing data after calling
     * this method will result in an error.
     *
     * @return string
     */
    public function cancel(): string {
        $this->dispose();
        return $this->buffer;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $data): Promise {
        return $this->send($data, false);
    }

    /**
     * {@inheritdoc}
     */
    public function end(string $data = ""): Promise {
        return $this->send($data, true);
    }

    private function send(string $data, bool $end = false): Promise {
        if ($this->generator === null) {
            throw new StreamException("The parser is no longer writable");
        }

        $this->buffer .= $data;

        try {
            while ($this->buffer !== "") {
                if (\is_int($this->delimiter)) {
                    if (\strlen($this->buffer) < $this->delimiter) {
                        break; // Too few bytes in buffer.
                    }

                    $length = $this->delimiter;
                } elseif (\is_string($this->delimiter)) {
                    if (($position = \strpos($this->buffer, $this->delimiter)) !== false) {
                        $length = $position + \strlen($this->delimiter);
                    } elseif ($end) { // Send remaining buffer to parser when ending.
                        $length = \strlen($this->buffer);
                    } else {
                        break; // Delimiter not found in buffer.
                    }
                } else {
                    $length = \strlen($this->buffer);
                }

                $send = \substr($this->buffer, 0, $length);
                $this->buffer = \substr($this->buffer, $length);

                try {
                    $this->delimiter = $this->generator->send($send);
                } catch (\Exception $exception) { // Wrap Exception instances into a StreamException.
                    $end = true;
                    throw new StreamException("The generator parser threw an exception", 0, $exception);
                } catch (\Throwable $exception) { // Rethrow Error instances.
                    $end = true;
                    throw $exception;
                }

                if (!$this->generator->valid()) {
                    $end = true;
                    break;
                }

                if ($this->delimiter !== null
                    && (!\is_int($this->delimiter) || $this->delimiter <= 0)
                    && (!\is_string($this->delimiter) || !\strlen($this->delimiter))
                ) {
                    throw new InvalidYieldError(
                        $this->generator,
                        \sprintf(
                            "Unexpected yield; Expected NULL, an int greater than 0, or a non-empty string; %s given",
                            \is_object($this->delimiter) ? \sprintf("instance of %s", \get_class($this->delimiter)) : \gettype($this->delimiter)
                        )
                    );
                }
            }

            return new Success(\strlen($data));
        } catch (\Throwable $exception) {
            return new Failure($exception);
        } finally {
            if ($end) {
                $this->dispose();
            }
        }
    }

    /**
     * Nulls the generator in a try/catch block and forwards any thrown exceptions from finally blocks to the loop
     * error handler.
     */
    private function dispose() {
        try {
            $this->generator = null; // May throw from finally blocks.
        } catch (\Throwable $exception) {
            Loop::defer(function () use ($exception) {
                throw $exception;
            });
        }
    }
}
