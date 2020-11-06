<?php

namespace Amp\ByteStream;

final class InputStreamChain implements InputStream
{
    /** @var InputStream[] */
    private array $streams;

    private bool $reading = false;

    public function __construct(InputStream ...$streams)
    {
        $this->streams = $streams;
    }

    /** @inheritDoc */
    public function read(): ?string
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
                $chunk = $this->streams[0]->read();
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
}
