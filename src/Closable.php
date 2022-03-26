<?php

namespace Amp\ByteStream;

interface Closable
{
    /**
     * Closes the stream or resource, marking it as unreadable and/or unwritable.
     *
     * - Whether pending reads are aborted or not is implementation dependent.
     * - New read operations should immediately return {@code null}.
     *
     * - Whether pending writes are aborted or not is implementation dependent.
     * - New write operations should throw.
     */
    public function close(): void;

    /**
     * Returns whether this resource has been closed.
     *
     * @return bool {@code true} if closed, otherwise {@code false}
     */
    public function isClosed(): bool;

    /**
     * Registers a callback that is invoked when this resource is closed.
     *
     * @param \Closure():void $onClose
     */
    public function onClose(\Closure $onClose): void;
}
