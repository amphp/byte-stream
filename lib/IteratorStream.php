<?php

namespace Amp\ByteStream;

final class IteratorStream implements InputStream
{
    private $iterator;
    private $exception;
    private $pending = false;
    private $firstRead = true;

    public function __construct(\Iterator $iterator)
    {
        $this->iterator = $iterator;
    }

    /** @inheritdoc */
    public function read(): ?string
    {
        if ($this->pending) {
            throw new PendingReadError;
        }

        $this->pending = true;

        if ($this->exception) {
            throw $this->exception;
        }

        try {
            if (!$this->firstRead) {
                $this->iterator->next();
            }

            if (!$this->iterator->valid()) {
                return null;
            }

            $this->firstRead = false;
            $chunk = $this->iterator->current();

            if (!\is_string($chunk)) {
                throw new StreamException(\sprintf(
                    "Unexpected iterator value of type '%s', expected string",
                    \is_object($chunk) ? \get_class($chunk) : \gettype($chunk)
                ));
            }

            return $chunk;
        } catch (\Throwable $e) {
            if (!$e instanceof StreamException) {
                $e = new StreamException("Unexpected exception during read()", 0, $e);
            }

            $this->exception = $e;

            throw $e;
        } finally {
            $this->pending = false;
        }
    }
}
