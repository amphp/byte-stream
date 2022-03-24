<?php

namespace Amp\ByteStream;

use Amp\Pipeline\Queue;

/**
 * @template-implements \IteratorAggregate<int, string>
 */
final class WritableIterableStream implements WritableStream, \IteratorAggregate
{
    private Queue $queue;

    /** @var \Traversable<int, string> */
    private readonly iterable $iterable;

    private int $bufferSize;

    private readonly OnCloseRegistry $registry;

    public function __construct(int $bufferSize)
    {
        $this->queue = new Queue;
        $this->iterable = $this->queue->iterate();
        $this->bufferSize = $bufferSize;
        $this->registry = new OnCloseRegistry;
    }

    public function close(): void
    {
        if (!$this->queue->isComplete()) {
            $this->queue->complete();
        }

        $this->registry->call();
    }

    public function isClosed(): bool
    {
        return !$this->isWritable();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->registry->register($onClose);
    }

    public function write(string $bytes): void
    {
        if ($this->queue->isComplete()) {
            throw new ClosedException('The stream is no longer writable');
        }

        $length = \strlen($bytes);
        $this->bufferSize -= $length;

        $future = $this->queue->pushAsync($bytes)->finally(fn () => $this->bufferSize += $length);

        if ($this->bufferSize < 0) {
            $future->await();
        } else {
            $future->ignore();
        }
    }

    public function end(): void
    {
        if (!$this->queue->isComplete()) {
            $this->queue->complete();
        }
    }

    public function isWritable(): bool
    {
        return !$this->queue->isComplete();
    }

    public function getIterator(): \Traversable
    {
        return $this->iterable;
    }
}
