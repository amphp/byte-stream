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

    /** @var bool */
    private $autoClose = true;

    public function __construct($stream, int $chunkSize = self::DEFAULT_CHUNK_SIZE, $autoClose = true) {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new \Error("Expected a valid stream");
        }

        $meta = \stream_get_meta_data($stream);

        if (isset($meta["mode"]) && $meta["mode"] !== ""
            && strpos($meta["mode"], "r") === false
            && strpos($meta["mode"], "+") === false
        ) {
            throw new \Error("Expected a readable stream");
        }

        \stream_set_blocking($stream, false);
        \stream_set_read_buffer($stream, 0);

        $this->resource = $stream;
        $this->autoClose = $autoClose;

        $deferred = &$this->deferred;
        $readable = &$this->readable;

        $this->watcher = Loop::onReadable($this->resource, static function ($watcher, $stream) use (
            &$deferred, &$readable, $chunkSize
        ) {
            if ($deferred === null) {
                return;
            }

            // Error reporting suppressed since fread() produces a warning if the stream unexpectedly closes.
            $data = @\fread($stream, $chunkSize);

            if ($data === false || ($data === '' && (\feof($stream) || !\is_resource($stream)))) {
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
     * Note: If a class implements `InputStream` and `OutputStream`, `close()` will close both streams at once. If you
     * want to allow half-closed duplex streams, you must use different objects for input and output.
     *
     * @return void
     */
    public function close() {
        if ($this->resource === null) {
            return;
        }

        if ($this->autoClose && \is_resource($this->resource)) {
            @\fclose($this->resource);
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

    public function getResource() {
        return $this->resource;
    }

    public function __destruct() {
        if ($this->autoClose) {
            $this->close();
        }
    }
}