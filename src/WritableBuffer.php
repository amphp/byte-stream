<?php

namespace Amp\ByteStream;

use Amp\DeferredFuture;

final class WritableBuffer implements WritableStream
{
    private readonly DeferredFuture $deferredFuture;

    private string $contents = '';

    private bool $closed = false;

    public readonly OnCloseRegistry $registry;

    public function __construct()
    {
        $this->deferredFuture = new DeferredFuture;
        $this->registry = new OnCloseRegistry;
    }

    public function write(string $bytes): void
    {
        if ($this->closed) {
            throw new ClosedException("The stream has already been closed");
        }

        $this->contents .= $bytes;
    }

    public function end(): void
    {
        if ($this->closed) {
            throw new ClosedException("The stream has already been closed");
        }

        $this->close();
    }

    public function isWritable(): bool
    {
        return !$this->closed;
    }

    public function buffer(): string
    {
        return $this->deferredFuture->getFuture()->await();
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $this->deferredFuture->complete($this->contents);
        $this->contents = '';

        $this->registry->call();
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function onClose(\Closure $onClose): void
    {
        $this->registry->register($onClose);
    }
}
