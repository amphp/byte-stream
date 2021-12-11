<?php

namespace Amp\ByteStream;

interface ClosableStream
{
    /**
     * Closes the stream, marking it as unreadable and/or unwritable.
     *
     * - Whether pending reads are aborted or not is implementation dependent.
     * - New read operations should immediately return {@code null}.
     *
     * - Whether pending writes are aborted or not is implementation dependent.
     * - New write operations should throw.
     */
    public function close(): void;

    /**
     * Returns whether the stream has been closed.
     *
     * @return bool {@code true} if closed, otherwise {@code false}
     */
    public function isClosed(): bool;
}
