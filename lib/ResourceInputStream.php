<?php

namespace Amp\ByteStream;

use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;

/**
 * Input stream abstraction for PHP's stream resources.
 */
final class ResourceInputStream implements InputStream {
    const DEFAULT_CHUNK_SIZE = 8192;

    /** @var resource */
    private $resource;

    /** @var string */
    private $watcher;

    /** @var \Amp\Deferred|null */
    private $deferred;

    /** @var bool */
    private $readable = true;

    /**
     * @param resource $stream Stream resource.
     * @param int $chunkSize Chunk size per read operation.
     */
    public function __construct($stream, int $chunkSize = self::DEFAULT_CHUNK_SIZE) {
        if (!\is_resource($stream) || \get_resource_type($stream) !== 'stream') {
            throw new \Error("Expected a valid stream");
        }

        $meta = \stream_get_meta_data($stream);
        $useFread = $meta["stream_type"] === "udp_socket" || $meta["stream_type"] === "STDIO";

        if (\strpos($meta["mode"], "r") === false && \strpos($meta["mode"], "+") === false) {
            throw new \Error("Expected a readable stream");
        }

        \stream_set_blocking($stream, false);
        \stream_set_read_buffer($stream, 0);

        $this->resource = $stream;

        $deferred = &$this->deferred;
        $readable = &$this->readable;

        $this->watcher = Loop::onReadable($this->resource, static function ($watcher, $stream) use (&$deferred, &$readable, $chunkSize, $useFread) {
            if ($useFread) {
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
                Loop::cancel($watcher);
                $data = null; // Stream closed, resolve read with null.
            }

            $temp = $deferred;
            $deferred = null;
            $temp->resolve($data);

            if ($deferred === null) { // Only disable watcher if no further read was requested.
                Loop::disable($watcher);
            }
        });

        Loop::disable($this->watcher);
    }

    /** @inheritdoc */
    public function read(): Promise {
        if ($this->deferred !== null) {
            throw new PendingReadError;
        }

        if (!$this->readable) {
            return new Success; // Resolve with null on closed stream.
        }

        $this->deferred = new Deferred;
        Loop::enable($this->watcher);

        return $this->deferred->promise();
    }

    /**
     * Closes the stream forcefully. Multiple `close()` calls are ignored.
     *
     * This does only free the resource internally, the underlying file descriptor isn't closed. This is left to PHP's
     * garbage collection system.
     *
     * @return void
     */
    public function close() {
        if ($this->resource) {
            // Error suppression, as resource might already be closed
            $meta = @\stream_get_meta_data($this->resource);

            if ($meta && \strpos($meta["mode"], "+") !== false) {
                @\stream_socket_shutdown($this->resource, \STREAM_SHUT_RD);
            } else {
                @\fclose($this->resource);
            }
            $this->resource = null;
        }

        $this->free();
    }

    /**
     * Nulls reference to resource, marks stream unreadable, and succeeds any pending read with null.
     */
    private function free() {
        $this->readable = false;

        if ($this->deferred !== null) {
            $deferred = $this->deferred;
            $this->deferred = null;
            $deferred->resolve(null);
        }

        Loop::cancel($this->watcher);
    }

    /**
     * @return resource|null The stream resource or null if the stream has closed.
     */
    public function getResource() {
        return $this->resource;
    }

    /**
     * References the read watcher, so the loop keeps running in case there's an active read.
     *
     * @see Loop::reference()
     */
    public function reference() {
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
    public function unreference() {
        if (!$this->resource) {
            throw new \Error("Resource has already been freed");
        }

        Loop::unreference($this->watcher);
    }

    public function __destruct() {
        if ($this->resource !== null) {
            $this->free();
        }
    }
}
