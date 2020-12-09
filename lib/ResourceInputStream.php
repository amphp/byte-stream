<?php

namespace Amp\ByteStream;

use Amp\Loop;

/**
 * Input stream abstraction for PHP's stream resources.
 */
final class ResourceInputStream implements InputStream
{
    const DEFAULT_CHUNK_SIZE = 8192;

    /** @var resource|null */
    private $resource;

    private string $watcher;

    private ?\Fiber $fiber = null;

    private bool $readable = true;

    private int $chunkSize;

    private bool $useSingleRead;

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
        $this->useSingleRead = $useSingleRead;

        if (\strpos($meta["mode"], "r") === false && \strpos($meta["mode"], "+") === false) {
            throw new \Error("Expected a readable stream");
        }

        \stream_set_blocking($stream, false);
        \stream_set_read_buffer($stream, 0);

        $this->resource = &$stream;
        $this->chunkSize = &$chunkSize;

        $fiber = &$this->fiber;
        $readable = &$this->readable;
        $this->watcher = Loop::onReadable($this->resource, static function ($watcher) use (
            &$fiber,
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

            \assert($data !== false, "Trying to read from a previously fclose()'d resource. Do NOT manually fclose() resources the loop still has a reference to.");

            // Error suppression, because pthreads does crazy things with resources,
            // which might be closed during two operations.
            // See https://github.com/amphp/byte-stream/issues/32
            if ($data === '' && @\feof($stream)) {
                $readable = false;
                $stream = null;
                $data = null; // Stream closed, resolve read with null.
                Loop::cancel($watcher);
            } else {
                Loop::disable($watcher);
            }

            $temp = $fiber;
            $fiber = null;

            \assert($temp instanceof \Fiber);
            $temp->resume($data);
        });

        Loop::disable($this->watcher);
    }

    /** @inheritdoc */
    public function read(): ?string
    {
        if ($this->fiber !== null) {
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

        Loop::enable($this->watcher);
        $this->fiber = \Fiber::this();
        return \Fiber::suspend(Loop::get());
    }

    /**
     * Closes the stream forcefully. Multiple `close()` calls are ignored.
     */
    public function close(): void
    {
        if ($this->resource &&  \get_resource_type($this->resource) === 'stream') {
            // Error suppression, as resource might already be closed
            $meta = @\stream_get_meta_data($this->resource);

            if ($meta && \strpos($meta["mode"], "+") !== false) {
                @\stream_socket_shutdown($this->resource, \STREAM_SHUT_RD);
            } else {
                /** @psalm-suppress InvalidPropertyAssignmentValue */
                @\fclose($this->resource);
            }
        }

        $this->free();
    }

    /**
     * Nulls reference to resource, marks stream unreadable, and succeeds any pending read with null.
     */
    private function free(): void
    {
        $this->readable = false;
        $this->resource = null;

        if ($this->fiber !== null) {
            $fiber = $this->fiber;
            $this->fiber = null;
            Loop::defer(static fn() => $fiber->resume());
        }

        Loop::cancel($this->watcher);
    }

    /**
     * @return resource|null The stream resource or null if the stream has closed.
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
     * References the read watcher, so the loop keeps running in case there's an active read.
     *
     * @see Loop::reference()
     */
    public function reference(): void
    {
        if (!$this->resource) {
            throw new \Error("Resource has already been freed");
        }

        Loop::reference($this->watcher);
    }

    /**
     * Unreferences the read watcher, so the loop doesn't keep running even if there are active reads.
     *
     * @see Loop::unreference()
     */
    public function unreference(): void
    {
        if (!$this->resource) {
            throw new \Error("Resource has already been freed");
        }

        Loop::unreference($this->watcher);
    }

    public function __destruct()
    {
        if ($this->resource !== null) {
            $this->free();
        }
    }
}
