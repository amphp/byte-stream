<?php

namespace Amp\ByteStream;

use Amp\Deferred;
use Amp\Failure;
use Amp\Loop;
use Amp\Promise;

class ResourceInputStream implements InputStream {
    const DEFAULT_CHUNK_SIZE = 8192;

    /** @var resource */
    private $resource;

    /** @var string */
    private $watcher;

    /** @var \Amp\Deferred|null */
    private $deferred;

    /** @var bool */
    private $readable = true;

    public function __construct($stream, int $chunkSize = self::DEFAULT_CHUNK_SIZE) {
        if (!\is_resource($stream) || \get_resource_type($stream) !== 'stream') {
            throw new \Error("Expected a valid stream");
        }

        $meta = \stream_get_meta_data($stream);

        if (isset($meta["mode"]) && $meta["mode"] !== ""
            && \strpos($meta["mode"], "r") === false
            && \strpos($meta["mode"], "+") === false
        ) {
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

            if ($data === false || ($data === '' && (\feof($stream) || !\is_resource($stream)))) {
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

    /**
     * Reads data from the stream.
     *
     * @return Promise Resolves with a string when new data is available or `null` if the stream has closed.
     *
     * @throws PendingReadException Thrown if another read operation is still pending.
     */
    public function read(): Promise {
        if ($this->deferred !== null) {
            throw new PendingReadException;
        }

        if (!$this->readable) {
            return new Failure(new ClosedException("The stream has been closed"));
        }

        $this->deferred = new Deferred;
        Loop::enable($this->watcher);

        return $this->deferred->promise();
    }

    /**
     * Closes the stream forcefully. Multiple `close()` calls are ignored.
     *
     * @return void
     */
    protected function close() {
        if ($this->resource === null) {
            return;
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
}
