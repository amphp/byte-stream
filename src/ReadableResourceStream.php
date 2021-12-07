<?php

namespace Amp\ByteStream;

use Amp\Cancellation;
use Amp\CancelledException;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

/**
 * Input stream abstraction for PHP's stream resources.
 */
final class ReadableResourceStream implements ReadableStream, ClosableStream, ResourceStream
{
    public const DEFAULT_CHUNK_SIZE = 8192;

    /** @var resource|null */
    private $resource;

    private string $watcher;

    private ?Suspension $suspension = null;

    private bool $readable = true;

    private int $chunkSize;

    private \Closure $cancel;

    /**
     * @param resource $stream Stream resource.
     * @param int      $chunkSize Chunk size per read operation.
     *
     * @throws \Error If an invalid stream or parameter has been passed.
     */
    public function __construct($stream, int $chunkSize = self::DEFAULT_CHUNK_SIZE)
    {
        if (!\is_resource($stream) || \get_resource_type($stream) !== 'stream') {
            throw new \Error("Expected a valid stream");
        }

        $meta = \stream_get_meta_data($stream);
        $useSingleRead = $meta["stream_type"] === "udp_socket" || $meta["stream_type"] === "STDIO";

        if (!\str_contains($meta["mode"], "r") && !\str_contains($meta["mode"], "+")) {
            throw new \Error("Expected a readable stream");
        }

        \stream_set_blocking($stream, false);
        \stream_set_read_buffer($stream, 0);

        $this->resource = &$stream;
        $this->chunkSize = &$chunkSize;

        $suspension = &$this->suspension;
        $readable = &$this->readable;
        $this->watcher = EventLoop::onReadable($this->resource, static function ($watcher) use (
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
                EventLoop::cancel($watcher);
            } else {
                EventLoop::disable($watcher);
            }

            \assert($suspension instanceof Suspension);

            $suspension->resume($data);
            $suspension = null;
        });

        $watcher = &$this->watcher;
        $this->cancel = static function (CancelledException $exception) use (&$suspension, $watcher): void {
            $suspension?->throw($exception);
            $suspension = null;
            EventLoop::disable($watcher);
        };

        EventLoop::disable($this->watcher);
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        if ($this->suspension !== null) {
            throw new PendingReadError;
        }

        if (!$this->readable) {
            return null; // Resolve with null on closed stream.
        }

        \assert($this->resource !== null);

        if (\feof($this->resource)) {
            $this->free();
            return null;
        }

        EventLoop::enable($this->watcher);
        $this->suspension = EventLoop::createSuspension();

        $id = $cancellation?->subscribe($this->cancel);

        try {
            return $this->suspension->suspend();
        } finally {
            /** @psalm-suppress PossiblyNullArgument If $cancellation is not null, $id will not be null. */
            $cancellation?->unsubscribe($id);
        }
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

    public function setChunkSize(int $chunkSize): void
    {
        $this->chunkSize = $chunkSize;
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

        EventLoop::reference($this->watcher);
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

        EventLoop::unreference($this->watcher);
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

        if ($this->suspension !== null) {
            $this->suspension->resume();
            $this->suspension = null;
        }

        EventLoop::cancel($this->watcher);
    }
}
