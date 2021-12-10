<?php

namespace Amp\ByteStream;

use Amp\Cancellation;

final class ReadableStreamChain implements ReadableStream
{
    /** @var ReadableStream[] */
    private array $sources;

    private bool $reading = false;

    public function __construct(ReadableStream ...$sources)
    {
        $this->sources = $sources;
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
    }

    public function isClosed(): bool
    {
        // TODO: Implement isClosed() method.
    }
}
