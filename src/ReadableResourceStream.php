<?php

namespace Amp\ByteStream;

use Amp\Cancellation;
use Amp\CancelledException;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

/**
 * Readable stream abstraction for PHP's stream resources.
 */
final class ReadableResourceStream implements ReadableStream, ResourceStream
{
    public const DEFAULT_CHUNK_SIZE = 8192;

    /** @var resource|null */
    private $resource;

    private string $callbackId;

    private ?Suspension $suspension = null;

    private bool $readable = true;

    private int $chunkSize;

    private bool $useSingleRead;

    private int $defaultChunkSize;

    private \Closure $cancel;

    /**
     * @param resource $stream Stream resource.
     * @param positive-int $chunkSize Default chunk size per read operation.
     *
     * @throws \Error If an invalid stream or parameter has been passed.
     */
    public function __construct($stream, int $chunkSize = self::DEFAULT_CHUNK_SIZE)
    {
        if (!\is_resource($stream) || \get_resource_type($stream) !== 'stream') {
            throw new \Error("Expected a valid stream");
        }

        $meta = \stream_get_meta_data($stream);
        $this->useSingleRead = $useSingleRead = $meta["stream_type"] === "udp_socket" || $meta["stream_type"] === "STDIO";

        if (!\str_contains($meta["mode"], "r") && !\str_contains($meta["mode"], "+")) {
            throw new \Error("Expected a readable stream");
        }

        if ($chunkSize <= 0) {
            throw new \ValueError('The chunk length must be a positive integer');
        }

        \stream_set_blocking($stream, false);
        \stream_set_read_buffer($stream, 0);

        $this->resource = &$stream;
        $this->defaultChunkSize = $this->chunkSize = &$chunkSize;

        $suspension = &$this->suspension;
        $readable = &$this->readable;

        $this->callbackId = EventLoop::disable(EventLoop::onReadable($this->resource, static function ($callbackId) use (
            &$suspension,
            &$readable,
            &$stream,
            &$chunkSize,
            $useSingleRead
        ): void {
            if ($useSingleRead) {
                $data = @\fread($stream, $chunkSize);
            } else {
                $data = @\stream_get_contents($stream, $chunkSize);
            }

            \assert(
                $data !== false,
                "Trying to read from a previously fclose()'d resource. Do NOT manually fclose() resources the loop still has a reference to."
            );

            // Error suppression, because pthreads does crazy things with resources,
            // which might be closed during two operations.
            // See https://github.com/amphp/byte-stream/issues/32
            if ($data === '' && @\feof($stream)) {
                $readable = false;
                $stream = null;
                $data = null; // Stream closed, resolve read with null.

                EventLoop::cancel($callbackId);
            } else {
                EventLoop::disable($callbackId);
            }

            \assert($suspension instanceof Suspension);

            $suspension->resume($data);
            $suspension = null;
        }));

        $callbackId = &$this->callbackId;
        $this->cancel = static function (CancelledException $exception) use (&$suspension, $callbackId): void {
            $suspension?->throw($exception);
            $suspension = null;

            EventLoop::disable($callbackId);
        };
    }

    /**
     * @param positive-int|null $maxLength
     */
    public function read(?Cancellation $cancellation = null, ?int $maxLength = null): ?string
    {
        $maxLength ??= $this->defaultChunkSize;

        if ($maxLength <= 0) {
            throw new \ValueError('The chunk length must be a positive integer');
        }

        if ($this->suspension !== null) {
            throw new PendingReadError;
        }

        if (!$this->readable) {
            return null; // Return null on closed stream.
        }

        \assert($this->resource !== null);

        // Attempt a direct read because PHP may buffer data, e.g. in TLS buffers.
        if ($this->useSingleRead) {
            $data = @\fread($this->resource, $maxLength);
        } else {
            $data = @\stream_get_contents($this->resource, $maxLength);
        }

        \assert(
            $data !== false,
            "Trying to read from a previously fclose()'d resource. Do NOT manually fclose() resources the loop still has a reference to."
        );

        if ($data === '') {
            if (\feof($this->resource)) {
                $this->free();

                return null;
            }

            $this->chunkSize = $maxLength;
            EventLoop::enable($this->callbackId);
            $this->suspension = EventLoop::createSuspension();

            $id = $cancellation?->subscribe($this->cancel);

            try {
                return $this->suspension->suspend();
            } finally {
                /** @psalm-suppress PossiblyNullArgument If $cancellation is not null, $id will not be null. */
                $cancellation?->unsubscribe($id);
            }
        }

        // Use a deferred suspension so other events are not starved by a stream that always has data available.
        $this->suspension = EventLoop::createSuspension();
        EventLoop::defer(function () use ($data): void {
            $this->suspension?->resume($data);
            $this->suspension = null;
        });

        return $this->suspension->suspend();
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * Closes the stream forcefully. Multiple `close()` calls are ignored.
     */
    public function close(): void
    {
        if (\is_resource($this->resource) && \get_resource_type($this->resource) === 'stream') {
            // Error suppression, as resource might already be closed
            $meta = \stream_get_meta_data($this->resource);

            if (\str_contains($meta["mode"], "+")) {
                \stream_socket_shutdown($this->resource, \STREAM_SHUT_RD);
            } else {
                /** @psalm-suppress InvalidPropertyAssignmentValue */
                \fclose($this->resource);
            }
        }

        $this->free();
    }

    public function isClosed(): bool
    {
        return $this->resource === null;
    }

    /**
     * @return resource|object|null The stream resource or null if the stream has closed.
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param positive-int $chunkSize
     */
    public function setChunkSize(int $chunkSize): void
    {
        if ($chunkSize <= 0) {
            throw new \ValueError('The chunk length must be a positive integer');
        }

        $this->defaultChunkSize = $chunkSize;
    }

    /**
     * References the readable watcher, so the loop keeps running in case there's an active read.
     *
     * @see EventLoop::reference()
     */
    public function reference(): void
    {
        if (!$this->resource) {
            return;
        }

        EventLoop::reference($this->callbackId);
    }

    /**
     * Unreferences the readable watcher, so the loop doesn't keep running even if there are active reads.
     *
     * @see EventLoop::unreference()
     */
    public function unreference(): void
    {
        if (!$this->resource) {
            return;
        }

        EventLoop::unreference($this->callbackId);
    }

    public function __destruct()
    {
        if ($this->resource !== null) {
            $this->free();
        }
    }

    /**
     * Nulls reference to resource, marks stream unreadable, and succeeds any pending read with null.
     */
    private function free(): void
    {
        $this->readable = false;
        $this->resource = null;

        $this->suspension?->resume();
        $this->suspension = null;

        EventLoop::cancel($this->callbackId);
    }
}
