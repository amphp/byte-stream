<?php

namespace Amp\ByteStream;

/**
 * A `WritableStream` allows writing data in chunks. Writers can wait on the returned promises to feel the backpressure.
 */
interface WritableStream extends ClosableStream
{
    /**
     * Writes data to the stream.
     *
     * @param string $bytes Bytes to write.
     *
     * @error ClosedException If the stream has already been closed.
     * @error StreamException If writing to the stream fails.
     */
    public function write(string $bytes): void;

    /**
     * Marks the stream as no longer writable.
     *
     * Note that this is not the same as forcefully closing the stream. This method waits for all pending writes to
     * complete before closing the stream. Socket streams implementing this interface should only close the writable
     * side of the stream.
     *
     * @error ClosedException If the stream has already been closed.
     * @error StreamException If writing to the stream fails.
     */
    public function end(): void;

    /**
     * @return bool A stream may no longer be writable if it is closed or ended using {@see end()}.
     */
    public function isWritable(): bool;

    /**
     * Closes the stream, marking it as unreadable and/or unwritable.
     *
     * Whether pending writes are aborted or not is implementation dependent. New write operations should throw.
     */
    public function close(): void;

    /**
     * Returns whether the stream has been closed.
     *
     * @return bool {@code true} if closed, otherwise {@code false}
     */
    public function isClosed(): bool;
}
