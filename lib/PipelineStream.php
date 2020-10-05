<?php

namespace Amp\ByteStream;

use Amp\Pipeline;

final class PipelineStream implements InputStream
{
    /** @var Pipeline<string> */
    private Pipeline $pipeline;

    private ?\Throwable $exception = null;

    private bool $pending = false;

    /**
     * @psalm-param Stream<string> $iterator
     */
    public function __construct(Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /** @inheritdoc */
    public function read(): ?string
    {
        if ($this->exception) {
            throw $this->exception;
        }

        if ($this->pending) {
            throw new PendingReadError;
        }

        $this->pending = true;

        try {
            if (null === $chunk = $this->pipeline->continue()) {
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
}
