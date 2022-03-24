<?php

namespace Amp\ByteStream;

use Amp\Cancellation;

final class ReadableStreamChain implements ReadableStream
{
    /** @var ReadableStream[] */
    private array $sources;

    private bool $reading = false;

    private readonly OnCloseRegistry $registry;

    public function __construct(ReadableStream ...$sources)
    {
        $this->sources = $sources;
        $this->registry = new OnCloseRegistry;
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        if ($this->reading) {
            throw new PendingReadError;
        }

        if (!$this->sources) {
            return null;
        }

        $this->reading = true;

        try {
            while ($this->sources) {
                $chunk = $this->sources[0]->read($cancellation);
                if ($chunk === null) {
                    \array_shift($this->sources);
                    continue;
                }

                return $chunk;
            }

            return null;
        } finally {
            $this->reading = false;
        }
    }

    public function isReadable(): bool
    {
        return !empty($this->sources);
    }

    public function close(): void
    {
        $sources = $this->sources;
        $this->sources = [];

        foreach ($sources as $source) {
            $source->close();
        }

        $this->registry->call();
    }

    public function isClosed(): bool
    {
        return !$this->isReadable();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->registry->register($onClose);
    }
}
