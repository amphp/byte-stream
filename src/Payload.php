<?php

namespace Amp\ByteStream;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Revolt\EventLoop;
use function Amp\async;

/**
 * Creates a buffered message from a ReadableStream.
 *
 * The message can be consumed in chunks using the read() API, or it may be buffered and accessed in its entirety by
 * calling buffer(). Once buffering is requested through buffer(), the stream cannot be read in chunks. On destruct any
 * remaining data is read from the ReadableStream given to this class.
 */
class Payload implements ReadableStream
{
    private ReadableStream|string|null $stream;

    private ?Future $future = null;

    private ?Future $lastRead = null;

    public function __construct(ReadableStream|string $stream)
    {
        $this->stream = match (true) {
            $stream instanceof ReadableBuffer => $stream->read(),
            default => $stream,
        };
    }

    public function __destruct()
    {
        if ($this->stream instanceof ReadableStream && !$this->future && $this->lastRead) {
            $stream = $this->stream;
            $lastRead = $this->lastRead;
            EventLoop::queue(static fn () => self::consume($stream, $lastRead));
        }
    }

    /**
     * @throws \Error If a buffered message was requested by calling buffer().
     */
    final public function read(?Cancellation $cancellation = null): ?string
    {
        if ($this->future) {
            throw new \Error("Cannot stream message data once a buffered message has been requested");
        }

        if ($this->stream instanceof ReadableStream) {
            $deferredFuture = new DeferredFuture;
            $this->lastRead = $deferredFuture->getFuture();
            $this->lastRead->ignore();

            try {
                $chunk = $this->stream->read($cancellation);

                $deferredFuture->complete($chunk !== null);
            } catch (\Throwable $exception) {
                $deferredFuture->error($exception);

                throw $exception;
            }

            return $chunk;
        }

        $chunk = $this->stream;
        $this->stream = null;

        return $chunk;
    }

    final public function isReadable(): bool
    {
        return $this->stream instanceof ReadableStream
            ? $this->stream->isReadable()
            : $this->stream !== null;
    }

    /**
     * Buffers the entire message and completes the returned future then.
     *
     * @return string The entire message contents.
     */
    final public function buffer(?Cancellation $cancellation = null): string
    {
        if ($this->future) {
            return $this->future->await($cancellation);
        }

        if ($this->stream instanceof ReadableStream) {
            $stream = $this->stream;
            $lastRead = $this->lastRead;
            $this->future = async(static function () use ($stream, $lastRead): string {
                if ($lastRead && !$lastRead->await()) {
                    return '';
                }

                $buffer = '';
                while (null !== $chunk = $stream->read()) {
                    $buffer .= $chunk;
                }

                return $buffer;
            });

            return $this->future->await($cancellation);
        }

        $payload = $this->stream ?? '';

        $this->future = Future::complete($payload);
        $this->stream = null;

        return $payload;
    }

    private static function consume(ReadableStream $stream, Future $lastRead): void
    {
        try {
            if (!$lastRead->await()) {
                return;
            }

            while (null !== $stream->read()) {
                // Discard unread bytes from message.
            }
        } catch (\Throwable) {
            // If exception is thrown here the stream completed anyway.
        }
    }
}
