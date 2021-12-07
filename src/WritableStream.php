<?php

namespace Amp\ByteStream;

use Amp\Future;

/**
 * A `WritableStream` allows writing data in chunks. Writers can wait on the returned promises to feel the backpressure.
 */
interface WritableStream
{
    /**
     * Writes data to the stream.
     *
     * @param string $data Bytes to write.
     *
     * @return Future<null> Completes once the data has been successfully written to the stream.
     *
     * @error ClosedException If the stream has already been closed.
     * @error StreamException If writing to the stream fails.
     */
    public function write(string $data): Future;

    /**
     * Marks the stream as no longer writable. Optionally writes a final data chunk before. Note that this is not the
     * same as forcefully closing the stream. This method waits for all pending writes to complete before closing the
     * stream. Socket streams implementing this interface should only close the writable side of the stream.
     *
     * @param string $finalData Bytes to write.
     *
     * @return Future<null> Completes once the data has been successfully written to the stream.
     *
     * @error ClosedException If the stream has already been closed.
     * @error StreamException If writing to the stream fails.
     */
    public function end(string $finalData = ""): Future;

    /**
     * @return bool A stream may no longer be writable if it is closed or ended using {@see end()}.
     */
    public function isWritable(): bool;
}
