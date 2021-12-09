<?php

namespace Amp\ByteStream;

use Amp\Pipeline\Emitter;

final class EmitterStream implements WritableStream, ClosableStream
{
    private Emitter $emitter;
    private int $bufferSize;

    public function __construct(Emitter $emitter, int $bufferSize)
    {
        $this->emitter = $emitter;
        $this->bufferSize = $bufferSize;
    }

    public function close(): void
    {
        if (!$this->emitter->isComplete()) {
            $this->emitter->complete();
        }
    }

    public function isClosed(): bool
    {
        return !$this->isWritable();
    }

    public function write(string $bytes): void
    {
        if ($this->emitter->isComplete()) {
            throw new ClosedException('The stream is no longer writable');
        }

        $length = \strlen($bytes);
        $this->bufferSize -= $length;

        $future = $this->emitter->emit($bytes)->finally(function () use ($length) {
            $this->bufferSize += $length;
        });

        if ($this->bufferSize < 0) {
            $future->await();
        } else {
            $future->ignore();
        }
    }

    public function end(): void
    {
        if (!$this->emitter->isComplete()) {
            $this->emitter->complete();
        }
    }

    public function isWritable(): bool
    {
        return !$this->emitter->isComplete();
    }
}
