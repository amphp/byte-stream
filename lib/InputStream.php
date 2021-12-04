<?php

namespace Amp\ByteStream;

use Amp\Cancellation;

/**
 * An `InputStream` allows reading byte streams in chunks.
 *
 * **Example**
 *
 * ```php
 * function readAll(InputStream $in): string {
 *     $buffer = "";
 *
 *     while (($chunk = $in->read()) !== null) {
 *         $buffer .= $chunk;
 *     }
 *
 *     return $buffer;
 * }
 * ```
 */
interface InputStream
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
}
