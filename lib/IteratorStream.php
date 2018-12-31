<?php

namespace Amp\ByteStream;

use Concurrent\Deferred;
use Concurrent\Stream\PendingReadException;
use Concurrent\Stream\ReadableStream;
use Concurrent\Stream\StreamClosedException;
use Concurrent\Stream\StreamException;
use Concurrent\Task;
use function Concurrent\race;

final class IteratorStream implements ReadableStream
{
    private $iterator;
    private $exception;
    private $pending = false;
    private $firstRead = true;
    private $buffer = '';
    private $failure;

    public function __construct(\Iterator $iterator)
    {
        $this->iterator = $iterator;
        $this->failure = new Deferred;
    }

    /** @inheritdoc */
    public function read(?int $length = null): ?string
    {
        if ($this->exception) {
            throw $this->exception;
        }

        if ($this->pending) {
            throw new PendingReadException('Cannot read from stream while another read is pending');
        }

        $length = $length ?? 8192;

        if ($length < 0) {
            throw new StreamException('Reading length can\'t be negative');
        }

        if ($length === 0) {
            return '';
        }

        $this->pending = true;

        try {
            while (\strlen($this->buffer) < $length) {
                if ($this->firstRead) {
                    $valid = Task::await(race([
                        Task::async([$this->iterator, 'valid']),
                        $this->failure->awaitable(),
                    ]));
                } else {
                    Task::await(race([
                        Task::async([$this->iterator, 'next']),
                        $this->failure->awaitable(),
                    ]));

                    $valid = $this->iterator->valid();
                }

                if (!$valid) {
                    break;
                }

                $this->firstRead = false;
                $chunk = $this->iterator->current();

                if (!\is_string($chunk)) {
                    throw new StreamException(\sprintf(
                        "Unexpected iterator value of type '%s', expected string",
                        \is_object($chunk) ? \get_class($chunk) : \gettype($chunk)
                    ));
                }

                $this->buffer .= $chunk;
                unset($chunk);
            }

            if ($this->buffer === '') {
                return null;
            }

            $chunk = \substr($this->buffer, 0, $length);
            $this->buffer = \substr($this->buffer, \strlen($chunk));

            return $chunk;
        } catch (\Throwable $e) {
            if (!$e instanceof StreamClosedException) {
                $e = new StreamClosedException("Unexpected exception during IteratorStream::read({$length})", 0, $e);
            }

            $this->exception = $e;

            throw $this->exception;
        } finally {
            $this->pending = false;
        }
    }

    /** @inheritdoc */
    public function close(?\Throwable $e = null): void
    {
        if ($this->exception === null) {
            $this->exception = new StreamClosedException('Cannot read from closed stream', 0, $e);
            $this->failure->fail($this->exception);
        }
    }
}
