<?php

namespace Amp\ByteStream;

interface ReferencedStream
{
    /**
     * References the read watcher, so the loop keeps running in case there's an active read.
     *
     * @see EventLoop::reference()
     */
    public function reference(): void;

    /**
     * Unreferences the read watcher, so the loop doesn't keep running even if there are active reads.
     *
     * @see EventLoop::unreference()
     */
    public function unreference(): void;
}
