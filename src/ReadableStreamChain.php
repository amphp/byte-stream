<?php

namespace Amp\ByteStream;

use Amp\Cancellation;

final class ReadableStreamChain implements ReadableStream
{
    /** @var ReadableStream[] */
    private array $streams;

    private bool $reading = false;

    public function __construct(ReadableStream ...$streams)
    {
        $this->streams = $streams;
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        if ($this->reading) {
            throw new PendingReadError;
        }

        if (!$this->streams) {
            return null;
        }

        $this->reading = true;

        try {
            while ($this->streams) {
                $chunk = $this->streams[0]->read($cancellation);
                if ($chunk === null) {
                    \array_shift($this->streams);
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
        return !empty($this->streams);
    }
}
