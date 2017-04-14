<?php

namespace Amp\ByteStream;

use Amp\Promise;

interface ReadableStream {
    /**
     * Returns a promise that resolves with a boolean, true if there is another chunk available, false if the stream
     * has ended.
     *
     * @return bool
     */
    public function advance(): Promise;

    /**
     * Gets the current chunk that arrived on the stream.
     *
     * @return string
     */
    public function getChunk(): string;
}
