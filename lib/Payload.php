<?php

namespace Amp\ByteStream;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Future;
use Revolt\EventLoop;
use function Amp\launch;

/**
 * Creates a buffered message from an InputStream. The message can be consumed in chunks using the read() API or it may
 * be buffered and accessed in its entirety by calling buffer(). Once buffering is requested through buffer(), the
 * stream cannot be read in chunks. On destruct any remaining data is read from the InputStream given to this class.
 */
class Payload implements InputStream
{
    private InputStream|string|null $stream;

    private Future $future;

    private Future $lastRead;

    public function __construct(InputStream|string $stream)
    {
        $this->stream = match (true) {
            $stream instanceof InMemoryStream => $stream->read(),
            default => $stream,
        };
    }

    public function __destruct()
    {
        if (!isset($this->future) && isset($this->lastRead)) {
            $stream = $this->stream;
            $lastRead = $this->lastRead;
            EventLoop::queue(static fn () => self::consume($stream, $lastRead));
        }
    }

    /**
     * @inheritdoc
     *
     * @throws \Error If a buffered message was requested by calling buffer().
     */
    final public function read(?CancellationToken $token = null): ?string
    {
        if (isset($this->future)) {
            throw new \Error("Cannot stream message data once a buffered message has been requested");
        }

        if ($this->stream instanceof InputStream) {
            $deferred = new Deferred;
            $this->lastRead = $deferred->getFuture();
            $this->lastRead->ignore();

            try {
                $chunk = $this->stream->read($token);
                $deferred->complete($chunk !== null);
            } catch (\Throwable $exception) {
                $deferred->error($exception);
                throw $exception;
            }

            return $chunk;
        }

        $chunk = $this->stream;
        $this->stream = null;
        return $chunk;
    }

    /**
     * Buffers the entire message and resolves the returned promise then.
     *
     * @return string The entire message contents.
     */
    final public function buffer(?CancellationToken $token = null): string
    {
        if (isset($this->future)) {
            return $this->future->await($token);
        }

        if ($this->stream instanceof InputStream) {
            $this->future = launch(function (): string {
                $buffer = '';
                if (isset($this->lastRead) && !$this->lastRead->await()) {
                    return $buffer;
                }

                while (null !== $chunk = $this->stream->read()) {
                    $buffer .= $chunk;
                }

                return $buffer;
            });

            return $this->future->await($token);
        }

        $payload = $this->stream ?? '';
        $this->future = Future::complete($payload);
        $this->stream = null;
        return $payload;
    }

    private static function consume(InputStream $stream, Future $lastRead): void
    {
        try {
            if (!$lastRead->await()) {
                return;
            }

            while (null !== $stream->read()) {
                // Discard unread bytes from message.
            }
        } catch (\Throwable $exception) {
            // If exception is thrown here the stream completed anyway.
        }
    }
}
