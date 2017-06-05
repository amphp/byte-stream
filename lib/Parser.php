<?php

namespace Amp\ByteStream;

use Amp\InvalidYieldError;

final class Parser {
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
            $this->generator = null;
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
        $this->generator = null;
        return $this->buffer;
    }

    public function push(string $data) {
        if ($this->generator === null) {
            throw new StreamException("The parser is no longer writable");
        }

        $this->buffer .= $data;
        $end = false;

        try {
            while ($this->buffer !== "") {
                if (\is_int($this->delimiter)) {
                    if (\strlen($this->buffer) < $this->delimiter) {
                        break; // Too few bytes in buffer.
                    }

                    $send = \substr($this->buffer, 0, $this->delimiter);
                    $this->buffer = \substr($this->buffer, $this->delimiter);
                } elseif (\is_string($this->delimiter)) {
                    if (($position = \strpos($this->buffer, $this->delimiter)) === false) {
                        break;
                    }

                    $send = \substr($this->buffer, 0, $position - \strlen($this->delimiter));
                    $this->buffer = \substr($this->buffer, $position);
                } else {
                    $send = $this->buffer;
                    $this->buffer = "";
                }

                try {
                    $this->delimiter = $this->generator->send($send);
                } catch (\Exception $exception) { // Wrap Exception instances into a StreamException.
                    throw new StreamException("The generator parser threw an exception", 0, $exception);
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
        } catch (\Throwable $exception) {
            $end = true;
            throw $exception;
        } finally {
            if ($end) {
                $this->generator = null;
            }
        }
    }
}
