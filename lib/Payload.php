<?php

namespace Amp\ByteStream;

use Amp\Deferred;
use Amp\Future;
use function Amp\coroutine;
use function Revolt\EventLoop\queue;

/**
 * Creates a buffered message from an InputStream. The message can be consumed in chunks using the read() API or it may
 * be buffered and accessed in its entirety by calling buffer(). Once buffering is requested through buffer(), the
 * stream cannot be read in chunks. On destruct any remaining data is read from the InputStream given to this class.
 */
class Payload implements InputStream
{
    private InputStream $stream;

    private Future $future;

    private Future $lastRead;

    public function __construct(InputStream $stream)
    {
        $this->stream = $stream;
    }

    public function __destruct()
    {
        if (!isset($this->future)) {
            queue(fn () => $this->consume());
        }
    }

    /**
     * @inheritdoc
     *
     * @throws \Error If a buffered message was requested by calling buffer().
     */
    final public function read(): ?string
    {
        if (isset($this->future)) {
            throw new \Error("Cannot stream message data once a buffered message has been requested");
        }

        $deferred = new Deferred;
        $this->lastRead = $deferred->getFuture();
        $this->lastRead->ignore();

        try {
            $chunk = $this->stream->read();
            $deferred->complete($chunk !== null);
        } catch (\Throwable $exception) {
            $deferred->error($exception);
            throw $exception;
        }

        return $chunk;
    }

    /**
     * Buffers the entire message and resolves the returned promise then.
     *
     * @return string The entire message contents.
     */
    final public function buffer(): string
    {
        if (isset($this->future)) {
            return $this->future->await();
        }

        return ($this->future = coroutine(function (): string {
            $buffer = '';
            if (isset($this->lastRead) && !$this->lastRead->await()) {
                return $buffer;
            }

            while (null !== $chunk = $this->stream->read()) {
                $buffer .= $chunk;
            }

            return $buffer;
        }))->await();
    }

    private function consume(): void
    {
        try {
            if (isset($this->lastRead) && !$this->lastRead->await()) {
                return;
            }

            while (null !== $this->stream->read()) {
                // Discard unread bytes from message.
            }
        } catch (\Throwable $exception) {
            // If exception is thrown here the stream completed anyway.
        }
    }
}
