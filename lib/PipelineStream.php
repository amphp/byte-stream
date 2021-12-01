<?php

namespace Amp\ByteStream;

use Amp\CancellationToken;
use Amp\Pipeline\Pipeline;

final class PipelineStream implements InputStream
{
    /** @var Pipeline<string> */
    private Pipeline $pipeline;

    private \Throwable $exception;

    private bool $pending = false;

    /**
     * @psalm-param Pipeline<string> $pipeline
     */
    public function __construct(Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /** @inheritdoc */
    public function read(?CancellationToken $token = null): ?string
    {
        if (isset($this->exception)) {
            throw $this->exception;
        }

        if ($this->pending) {
            throw new PendingReadError;
        }

        $this->pending = true;

        try {
            if (null === $chunk = $this->pipeline->continue($token)) {
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
