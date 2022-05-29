<?php

namespace Amp\ByteStream;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\Pipeline\ConcurrentIterableIterator;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Pipeline;

final class ReadableIterableStream implements ReadableStream
{
    /** @var ConcurrentIterator<string>|null */
    private ?ConcurrentIterator $iterator;

    private ?\Throwable $exception = null;

    private bool $pending = false;

    private readonly DeferredFuture $onClose;

    /**
     * @param iterable<mixed, string> $iterable
     */
    public function __construct(iterable $iterable)
    {
        /** @psalm-suppress TypeDoesNotContainType */
        if ($iterable instanceof Pipeline) {
            $iterable = $iterable->getIterator();
        }

        $this->iterator = $iterable instanceof ConcurrentIterator
            ? $iterable
            : new ConcurrentIterableIterator($iterable);

        $this->onClose = new DeferredFuture;
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        if ($this->exception) {
            throw $this->exception;
        }

        if ($this->pending) {
            throw new PendingReadError;
        }

        if ($this->iterator === null) {
            return null;
        }

        $this->pending = true;

        try {
            if (!$this->iterator->continue($cancellation)) {
                $this->iterator = null;
                return null;
            }

            $chunk = $this->iterator->getValue();

            if (!\is_string($chunk)) {
                throw new StreamException(\sprintf(
                    "Unexpected iterable value of type %s, expected string",
                    \get_debug_type($chunk)
                ));
            }

            return $chunk;
        } catch (\Throwable $exception) {
            if ($exception instanceof CancelledException && $cancellation->isRequested()) {
                throw $exception; // Read cancelled, stream did not fail.
            }

            throw $this->exception = $exception instanceof StreamException
                ? $exception
                : new StreamException("Iterable threw an exception", 0, $exception);
        } finally {
            $this->pending = false;
        }
    }

    public function isReadable(): bool
    {
        return $this->iterator !== null;
    }

    public function close(): void
    {
        $this->iterator?->dispose();
        $this->iterator = null;

        if (!$this->onClose->isComplete()) {
            $this->onClose->complete();
        }
    }

    public function isClosed(): bool
    {
        return !$this->isReadable();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->onClose->getFuture()->finally($onClose);
    }
}
