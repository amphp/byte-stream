<?php

namespace Amp\ByteStream;

/**
 * An `InputStream` allows reading byte streams in chunks.
 *
 * **Example**
 *
 * ```php
 * function readAll(InputStream $in): Promise {
 *     $buffer = "";
 *
 *     while (null !== $chunk = $in->read()) {
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
     * @return string Available data or `null` if the stream has closed.
     *
     * @throws PendingReadError Thrown if another read operation is still pending.
     */
    public function read(): ?string;
}
