<?php

namespace Amp\ByteStream\Internal;

use Amp\ByteStream\ClosableStream;
use Amp\ByteStream\ClosedException;
use Amp\ByteStream\WritableStream;
use Amp\Pipeline\Emitter;

/** @internal */
final class EmitterStream implements WritableStream, ClosableStream
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
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

        $this->emitter->emit($bytes)->ignore();
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
