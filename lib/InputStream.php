<?php

namespace Amp\ByteStream;

use Amp\Promise;

/**
 * An `InputStream` allows reading byte streams in chunks.
 *
 * **Example**
 *
 * ```php
 * function readAll(InputStream $in): Promise {
 *     return Amp\call(function () use ($in) {
 *         $buffer = "";
 *
 *         while (($chunk = yield $in->read()) !== null) {
 *             $buffer .= $chunk;
 *         }
 *
 *         return $buffer;
 *     });
 * }
 * ```
 */
interface InputStream {
    /**
     * Reads data from the stream.
     *
     * @return Promise Resolves with a string when new data is available or `null` if the stream has closed.
     *
     * @throws PendingReadException Thrown if another read operation is still pending.
     */
    public function read(): Promise;

    /**
     * Closes the stream forcefully. Multiple `close()` calls are ignored.
     *
     * Note: If a class implements `InputStream` and `OutputStream`, `close()` will close both streams at once. If you
     * want to allow half-closed duplex streams, you must use different objects for input and output.
     *
     * @return void
     */
    public function close();
}
