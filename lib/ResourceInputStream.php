<?php

namespace Amp\ByteStream;

use Amp\Emitter;
use Amp\Iterator;
use Amp\Loop;
use Amp\Promise;
use function Amp\call;

class ResourceInputStream implements InputStream {
    /** @var resource */
    private $resource;

    /** @var string */
    private $watcher;

    /** @var Emitter */
    private $emitter;

    /** @var Iterator */
    private $iterator;

    /** @var bool */
    private $autoClose = true;

    /** @var Promise|null */
    private $readOperation;

    public function __construct($stream, int $chunkSize = 8192, $autoClose = true) {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new \Error("Expected a valid stream");
        }

        $meta = \stream_get_meta_data($stream);

        if (isset($meta["mode"]) && $meta["mode"] !== "" && strpos($meta["mode"], "r") === false && strpos($meta["mode"], "+") === false) {
            throw new \Error("Expected a readable stream");
        }

        \stream_set_blocking($stream, false);
        \stream_set_read_buffer($stream, 0);

        $this->resource = $stream;
        $this->emitter = new Emitter;
        $this->iterator = $this->emitter->getIterator();
        $this->autoClose = $autoClose;

        $emitter = &$this->emitter;

        $this->watcher = Loop::onReadable($this->resource, static function ($watcher, $stream) use (&$emitter, $chunkSize) {
            // Error reporting suppressed since fread() produces a warning if the stream unexpectedly closes.
            $data = @\fread($stream, $chunkSize);

            if ($data === false || ($data === '' && (\feof($stream) || !\is_resource($stream)))) {
                Loop::cancel($watcher);
                $temp = $emitter;
                $emitter = null;
                $temp->complete();
                return;
            }

            Loop::disable($watcher);

            $emitter->emit($data)->onResolve(function ($exception) use (&$emitter, $watcher) {
                if ($emitter !== null && $exception === null) {
                    Loop::enable($watcher);
                }
            });
        });
    }

    /**
     * Reads data from the stream.
     *
     * @return Promise Resolves with a string when new data is available or `null` if the stream has closed.
     *
     * @throws PendingReadException Thrown if another read operation is still pending.
     */
    public function read(): Promise {
        if ($this->readOperation !== null) {
            throw new PendingReadException;
        }

        Loop::enable($this->watcher);

        $this->readOperation = call(function () {
            if (yield $this->emitter->getIterator()->advance()) {
                $this->readOperation = null;
                return $this->emitter->getIterator()->getCurrent();
            }

            throw new ClosedException("The stream has been closed");
        });

        return $this->readOperation;
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

        if ($this->emitter !== null) {
            $temp = $this->emitter;
            $this->emitter = null;
            $temp->complete();
        }

        Loop::cancel($this->watcher);
    }

    public function __destruct() {
        if ($this->autoClose) {
            $this->close();
        }
    }
}