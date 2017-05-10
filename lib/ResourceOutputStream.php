<?php

namespace Amp\ByteStream;

use Amp\Deferred;
use Amp\Failure;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;

class ResourceOutputStream implements OutputStream {
    /** @var resource */
    private $resource;

    /** @var string */
    private $watcher;

    /** @var \SplQueue */
    private $writes;

    /** @var bool */
    private $writable = true;

    /** @var bool */
    private $autoClose = true;

    public function __construct($stream, int $chunkSize = 8192, bool $autoClose = true) {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new \Error("Expected a valid stream");
        }

        $meta = \stream_get_meta_data($stream);

        if (isset($meta["mode"]) && $meta["mode"] === "r") {
            throw new \Error("Expected a writable stream");
        }

        \stream_set_blocking($stream, false);
        \stream_set_write_buffer($stream, 0);

        $this->resource = $stream;
        $this->autoClose = $autoClose;

        $writes = $this->writes = new \SplQueue;
        $writable = &$this->writable;

        $this->watcher = Loop::onWritable($stream, static function ($watcher, $stream) use ($writes, &$writable) {
            try {
                while (!$writes->isEmpty()) {
                    /** @var \Amp\Deferred $deferred */
                    list($data, $previous, $deferred) = $writes->shift();
                    $length = \strlen($data);

                    if ($length === 0) {
                        $deferred->resolve(0);
                        continue;
                    }

                    // Error reporting suppressed since fwrite() emits E_WARNING if the pipe is broken or the buffer is full.
                    $written = @\fwrite($stream, $data);

                    if ($written === false || $written === 0) {
                        $writable = false;

                        $message = "Failed to write to socket";
                        if ($error = \error_get_last()) {
                            $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
                        }
                        $exception = new \Exception($message);
                        $deferred->fail($exception);
                        while (!$writes->isEmpty()) {
                            list(, , $deferred) = $writes->shift();
                            $deferred->fail($exception);
                        }
                        return;
                    }

                    if ($length <= $written) {
                        $deferred->resolve($written + $previous);
                        continue;
                    }

                    $data = \substr($data, $written);
                    $writes->unshift([$data, $written + $previous, $deferred]);
                    return;
                }
            } finally {
                if ($writes->isEmpty()) {
                    Loop::disable($watcher);
                }
            }
        });

        Loop::disable($this->watcher);
    }

    /**
     * Writes data to the stream.
     *
     * @param string $data Bytes to write.
     *
     * @return Promise Succeeds once the data has been successfully written to the stream.
     *
     * @throws ClosedException If the stream has already been closed.
     */
    public function write(string $data): Promise {
        return $this->send($data, false);
    }

    /**
     * Closes the stream after all pending writes have been completed. Optionally writes a final data chunk before.
     *
     * @param string $finalData Bytes to write.
     *
     * @return Promise Succeeds once the data has been successfully written to the stream.
     *
     * @throws ClosedException If the stream has already been closed.
     */
    public function end(string $finalData = ""): Promise {
        return $this->send($finalData, true);
    }

    /**
     * @param string $data
     * @param bool   $end
     *
     * @return Promise
     */
    private function send(string $data, bool $end = false): Promise {
        if (!$this->writable) {
            return new Failure(new \Exception("The stream is not writable"));
        }

        $length = \strlen($data);
        $written = 0;

        if ($end) {
            $this->writable = false;
        }

        if ($this->writes->isEmpty()) {
            if ($length === 0) {
                if ($end) {
                    $this->close();
                }
                return new Success(0);
            }

            // Error reporting suppressed since fwrite() emits E_WARNING if the pipe is broken or the buffer is full.
            $written = @\fwrite($this->resource, $data);

            if ($written === false) {
                $message = "Failed to write to stream";
                if ($error = \error_get_last()) {
                    $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
                }
                return new Failure(new StreamException($message));
            }

            if ($length === $written) {
                if ($end) {
                    $this->close();
                }
                return new Success($written);
            }

            $data = \substr($data, $written);
        }

        $deferred = new Deferred;
        $this->writes->push([$data, $written, $deferred]);
        Loop::enable($this->watcher);
        $promise = $deferred->promise();

        if ($end) {
            $promise->onResolve([$this, 'close']);
        }

        return $promise;
    }

    /**
     * @inheritdoc
     */
    public function close() {
        if ($this->resource !== null) {
            return;
        }

        if (\is_resource($this->resource)) {
            if (\substr(\stream_get_meta_data($this->resource)["stream_type"], 0, \strlen("tcp_socket"))  === "tcp_socket") {
                \stream_socket_shutdown($this->resource, \STREAM_SHUT_WR);
            } else {
                @\fclose($this->resource);
            }
        }

        $this->resource = null;
        $this->writable = false;

        if (!$this->writes->isEmpty()) {
            $exception = new ClosedException("The socket was closed before writing completed");
            do {
                /** @var \Amp\Deferred $deferred */
                list(, , $deferred) = $this->writes->shift();
                $deferred->fail($exception);
            } while (!$this->writes->isEmpty());
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
