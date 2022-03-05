<?php

namespace Amp\ByteStream;

use Amp\DeferredFuture;
use Amp\Future;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

/**
 * This class provides a tool for efficiently writing to a stream asynchronously. A single fiber is used for all
 * writes to the stream, while each write returns a {@see Future} instead of waiting for each write to complete before
 * returning controller to the caller.
 */
final class AsyncWriter
{
    private ?WritableStream $destination;

    /** @var \SplQueue<array{DeferredFuture, string|null}> */
    private \SplQueue $writeQueue;

    private ?Suspension $suspension = null;

    private bool $active = true;

    public function __construct(WritableStream $destination)
    {
        $this->destination = $destination;
        $this->writeQueue = $writeQueue = new \SplQueue;

        $suspension = &$this->suspension;
        $active = &$this->active;
        EventLoop::queue(static function () use ($destination, $writeQueue, &$suspension, &$active): void {
            while ($active && $destination->isWritable()) {
                if ($writeQueue->isEmpty()) {
                    $suspension = EventLoop::getSuspension();
                    $suspension->suspend();
                }

                while (!$writeQueue->isEmpty()) {
                    /**
                     * @var DeferredFuture $deferredFuture
                     * @var string|null $bytes
                     */
                    [$deferredFuture, $bytes] = $writeQueue->shift();

                    try {
                        if ($bytes !== null) {
                            $destination->write($bytes);
                        } else {
                            $destination->end();
                        }

                        $deferredFuture->complete();
                    } catch (\Throwable $exception) {
                        $this->active = false;
                        $deferredFuture->error($exception);
                        while (!$writeQueue->isEmpty()) {
                            [$deferredFuture] = $writeQueue->shift();
                            $deferredFuture->error($exception);
                        }
                        return;
                    }
                }
            }
        });
    }

    public function __destruct()
    {
        $this->active = false;
        $this->suspension?->resume();
        $this->suspension = null;
    }

    /**
     * Queues a chunk of data to be written to the stream, returning a {@see Future} that is completed once the data
     * has been written to the stream or errors if it cannot be written to the stream.
     *
     * @return Future<never>
     */
    public function write(string $bytes): Future
    {
        if (!$this->isWritable()) {
            return Future::error(new ClosedException('The destination stream is no longer writable'));
        }

        $deferredFuture = new DeferredFuture();
        $this->writeQueue->push([$deferredFuture, $bytes]);
        $this->suspension?->resume();
        $this->suspension = null;

        return $deferredFuture->getFuture();
    }

    /**
     * Closes the underlying WritableStream once all queued data has been written.
     *
     * @return Future<void>
     */
    public function end(): Future
    {
        if (!$this->isWritable()) {
            return Future::error(new ClosedException('The destination stream is no longer writable'));
        }

        $this->destination = null;

        $deferredFuture = new DeferredFuture();
        $this->writeQueue->push([$deferredFuture, null]);
        $this->suspension?->resume();
        $this->suspension = null;

        return $deferredFuture->getFuture();
    }

    public function isWritable(): bool
    {
        return $this->active && $this->destination?->isWritable();
    }
}
