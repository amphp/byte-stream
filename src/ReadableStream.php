<?php

namespace Amp\ByteStream;

use Amp\Cancellation;

/**
 * A `ReadableStream` allows reading byte streams in chunks.
 *
 * **Example**
 *
 * ```php
 * function readAll(ReadableStream $source): string {
 *     $buffer = "";
 *
 *     while (null !== $chunk = $source->read()) {
 *         $buffer .= $chunk;
 *     }
 *
 *     return $buffer;
 * }
 * ```
 */
interface ReadableStream extends ClosableStream
{
    /**
     * Reads data from the stream.
     *
     * @param Cancellation|null $cancellation Cancel the read operation. The state in which the stream will be after
     * a cancelled operation is implementation dependent.
     *
     * @return string|null Returns a string when new data is available or `null` if the stream has closed.
     *
     * @throws PendingReadError Thrown if another read operation is still pending.
     */
    public function read(?Cancellation $cancellation = null): ?string;

    /**
     * @return bool A stream may become unreadable if the underlying source is closed or lost.
     */
    public function isReadable(): bool;

    /**
     * Closes the stream, marking it as unreadable and/or unwritable.
     *
     * Whether pending reads are aborted or not is implementation dependent. New read operations should immediately
     * return {@code null}.
     */
    public function close(): void;

    /**
     * Returns whether the stream has been closed.
     *
     * @return bool {@code true} if closed, otherwise {@code false}
     */
    public function isClosed(): bool;
}
