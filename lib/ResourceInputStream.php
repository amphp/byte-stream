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

    /** @var bool Flag to avoid \fclose() inside destructor */
    private $inDestructor = false;

    /**
     * @param resource $stream Stream resource.
     * @param int $chunkSize Chunk size per `fread()` operation.
     */
    public function __construct($stream, int $chunkSize = self::DEFAULT_CHUNK_SIZE) {
        if (!\is_resource($stream) || \get_resource_type($stream) !== 'stream') {
            throw new \Error("Expected a valid stream");
        }

        $meta = \stream_get_meta_data($stream);

        if (\strpos($meta["mode"], "r") === false && \strpos($meta["mode"], "+") === false) {
            throw new \Error("Expected a readable stream");
        }

        \stream_set_blocking($stream, false);
        \stream_set_read_buffer($stream, 0);

        $this->resource = $stream;

        $deferred = &$this->deferred;
        $readable = &$this->readable;
        $resource = &$this->resource;

        $this->watcher = Loop::onReadable($this->resource, static function ($watcher, $stream) use (
            &$deferred, &$readable, &$resource, $chunkSize
        ) {
            if ($deferred === null) {
                return;
            }

            // Error reporting suppressed since fread() produces a warning if the stream unexpectedly closes.
            $data = @\fread($stream, $chunkSize);

            if ($data === false || ($data === '' && (!\is_resource($stream) || \feof($stream)))) {
                $readable = false;
                $resource = null;
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
        if ($this->resource && !$this->inDestructor) {
            $meta = \stream_get_meta_data($this->resource);

            if (strpos($meta["mode"], "+") !== false) {
                \stream_socket_shutdown($this->resource, \STREAM_SHUT_RD);
            } else {
                \fclose($this->resource);
            }
        }

        $this->resource = null;
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

    public function __destruct() {
        $this->inDestructor = true;

        if ($this->resource !== null) {
            $this->close();
        }
    }
}
