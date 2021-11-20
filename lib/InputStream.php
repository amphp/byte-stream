<?php

namespace Amp\ByteStream;

use Amp\CancellationToken;

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
     * @param CancellationToken|null $token Cancel the read operation. The state in which the stream will be after
     * a cancelled operation is implementation dependent.
     *
     * @return string|null Returns a string when new data is available or `null` if the stream has closed.
     *
     * @throws PendingReadError Thrown if another read operation is still pending.
     */
    public function read(?CancellationToken $token = null): ?string;
}
