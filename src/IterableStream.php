<?php

namespace Amp\ByteStream;

use Amp\Cancellation;
use Amp\Pipeline\Pipeline;
use function Amp\Pipeline\fromIterable;

final class IterableStream implements ReadableStream
{
    /** @var Pipeline<string> */
    private Pipeline $pipeline;

    private \Throwable $exception;

    private bool $pending = false;

    /**
     * @param iterable<string> $iterable
     */
    public function __construct(iterable $iterable)
    {
        $this->pipeline = $iterable instanceof Pipeline ? $iterable : fromIterable($iterable);
    }

    /** @inheritdoc */
    public function read(?Cancellation $cancellation = null): ?string
    {
        if (isset($this->exception)) {
            throw $this->exception;
        }

        if ($this->pending) {
            throw new PendingReadError;
        }

        $this->pending = true;

        try {
            if (null === $chunk = $this->pipeline->continue($cancellation)) {
                return null;
            }

            if (!\is_string($chunk)) {
                throw new StreamException(\sprintf(
                    "Unexpected pipeline value of type '%s', expected string",
                    \is_object($chunk) ? \get_class($chunk) : \gettype($chunk)
                ));
            }

            return $chunk;
        } catch (\Throwable $exception) {
            $this->exception = $exception instanceof StreamException
                ? $exception
                : new StreamException("Pipeline threw an exception", 0, $exception);
            throw $exception;
        } finally {
            $this->pending = false;
        }
    }

    public function isReadable(): bool
    {
        return !$this->pipeline->isComplete();
    }
}
