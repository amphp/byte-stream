<?php

namespace Amp\ByteStream;

use Amp\Cancellation;
use Amp\DeferredFuture;

/**
 * Creates a buffered message from a ReadableStream.
 *
 * The message can be consumed in chunks using the read() API, or it may be buffered and accessed in its entirety by
 * calling buffer(). Once buffering is requested through buffer(), the stream cannot be read in chunks.
 */
class Payload implements ReadableStream
{
    private const MODE_STREAM = 1;
    private const MODE_BUFFER = 2;

    private ReadableStream|string|null $stream;

    private int $mode = 0;

    private readonly DeferredFuture $onClose;

    public function __construct(ReadableStream|string $stream)
    {
        $this->stream = match (true) {
            $stream instanceof ReadableBuffer => $stream->read(),
            default => $stream,
        };

        $this->onClose = new DeferredFuture;

        if ($this->stream === null) {
            $this->close();
        }
    }

    public function __destruct()
    {
        if ($this->stream instanceof Closable) {
            $this->stream->close();
        }
    }

    final public function read(?Cancellation $cancellation = null): ?string
    {
        if ($this->mode === self::MODE_BUFFER) {
            throw new \Error('Can\'t stream payload after calling buffer()');
        }

        $this->mode = self::MODE_STREAM;

        if ($this->stream instanceof ReadableStream) {
            return $this->stream->read($cancellation);
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
     * Buffers the entire message.
     *
     * @param int $limit Only buffer up to the given number of bytes, throwing {@see BufferException} if exceeded.
     *
     * @return string The entire message contents.
     *
     * @throws StreamException
     * @throws BufferException
     */
    final public function buffer(?Cancellation $cancellation = null, int $limit = \PHP_INT_MAX): string
    {
        if ($this->mode === self::MODE_STREAM) {
            throw new \Error('Can\'t buffer payload after calling read()');
        }

        if ($this->mode === self::MODE_BUFFER) {
            throw new \Error('Can\'t buffer() a payload more than once');
        }

        $this->mode = self::MODE_BUFFER;

        if ($this->stream instanceof ReadableStream) {
            return buffer($this->stream, $cancellation, $limit);
        }

        $payload = $this->stream ?? '';
        $this->stream = null;

        return $payload;
    }

    public function close(): void
    {
        if ($this->stream instanceof ReadableStream) {
            $this->stream->close();
        }

        if (!$this->onClose->isComplete()) {
            $this->onClose->complete();
        }
    }

    public function isClosed(): bool
    {
        return !$this->isReadable();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->onClose->getFuture()->finally($onClose);
    }
}
