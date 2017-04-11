<?php

namespace Amp\ByteStream;

use Amp\Promise;

interface ReadableStream {
    /**
     * Determines if the stream is readable.
     *
     * @return bool
     */
    public function isReadable(): bool;

    /**
     * Reads bytes from the stream.
     *
     * @param int|null $bytes A number of bytes to read or null for any amount.
     *
     * @return \Amp\Promise<string> Resolves with bytes read from the stream.
     */
    public function read(int $bytes = null): Promise;

    /**
     * Reads bytes from the stream until the given delimiter is found in the stream, the max number of bytes has been
     * read given by $limit, or the stream ends.
     *
     * @param string $delimiter
     * @param int|null $limit Max number of bytes to read.
     *
     * @return \Amp\Promise<string> Resolves with bytes read from the stream, including the delimiter.
     */
    public function readTo(string $delimiter, int $limit = null): Promise;

    /**
     * Buffers all bytes arriving on the stream until the stream has ended.
     *
     * @return \Amp\Promise<string>
     */
    public function readAll(): Promise;
}
