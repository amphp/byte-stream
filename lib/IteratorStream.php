<?php

namespace Amp\ByteStream;

use Amp\Iterator;
use function Amp\Promise\await;

final class IteratorStream implements InputStream
{
    private $iterator;
    private $exception;
    private $pending = false;

    public function __construct(Iterator $iterator)
    {
        $this->iterator = $iterator;
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
            if (!await($this->iterator->advance())) {
                return null;
            }

            $chunk = $this->iterator->getCurrent();

            if (!\is_string($chunk)) {
                throw new StreamException(\sprintf(
                    "Unexpected iterator value of type '%s', expected string",
                    \is_object($chunk) ? \get_class($chunk) : \gettype($chunk)
                ));
            }

            return $chunk;
        } catch (\Throwable $e) {
            $this->exception = $e;
            throw $e;
        } finally {
            $this->pending = false;
        }
    }
}
