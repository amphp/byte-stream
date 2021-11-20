<?php

namespace Amp\ByteStream;

interface ClosableStream
{
    /**
     * Closes the stream, marking it as unreadable and/or unwritable.
     */
    public function close(): void;
}
