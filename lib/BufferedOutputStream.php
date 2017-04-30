<?php

namespace Amp\ByteStream;

use Amp\Promise;
use Amp\Success;

/**
 * Buffers writes up to the specified buffer size, which can be used to reduce the number of `write` system calls on the
 * destination stream.
 */
class BufferedOutputStream implements OutputStream {
    private $destination;
    private $chunkSize;
    private $buffer;
    private $closed;

    /**
     * @param OutputStream $destination Stream to write the buffered chunks to.
     * @param int          $chunkSize Chunk size to buffer.
     */
    public function __construct(OutputStream $destination, int $chunkSize = 8192) {
        $this->destination = $destination;
        $this->chunkSize = $chunkSize;
        $this->buffer = new Buffer;
        $this->closed = false;
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
        if ($this->closed) {
            throw new ClosedException("The stream has already been closed");
        }

        $this->buffer->push($data);

        if ($this->buffer->getLength() < $this->chunkSize) {
            return new Success;
        }

        return $this->destination->write($this->buffer->drain());
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
        $promise = $this->write($this->buffer->drain() . $finalData);
        $promise->onResolve([$this, "close"]);

        return $promise;
    }

    /**
     * Closes the stream forcefully. Multiple `close()` calls are ignored. Successful streams should always be closed
     * via `end()`.
     *
     * @return void
     */
    public function close() {
        $this->closed = true;
        $this->destination->close();
    }
}