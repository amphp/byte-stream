<?php

namespace Amp\ByteStream;

interface ClosableStream
{
    /**
     * Closes the stream, marking it as unreadable and/or unwritable.
     */
    public function close(): void;

    /**
     * Returns whether the stream has been closed.
     *
     * @return bool {@code true} if closed, otherwise {@code false}
     */
    public function isClosed(): bool;
}
