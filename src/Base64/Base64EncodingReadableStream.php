<?php

namespace Amp\ByteStream\Base64;

use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;

final class Base64EncodingReadableStream implements ReadableStream
{
    /** @var ReadableStream */
    private ReadableStream $source;

    /** @var string|null */
    private ?string $buffer = '';

    public function __construct(ReadableStream $source)
    {
        $this->source = $source;
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        $chunk = $this->source->read($cancellation);
        if ($chunk === null) {
            if ($this->buffer === null) {
                return null;
            }

            $chunk = \base64_encode($this->buffer);
            $this->buffer = null;

            return $chunk;
        }

        $this->buffer .= $chunk;

        $length = \strlen($this->buffer);
        $chunk = \base64_encode(\substr($this->buffer, 0, $length - $length % 3));
        $this->buffer = \substr($this->buffer, $length - $length % 3);

        return $chunk;
    }

    public function isReadable(): bool
    {
        return $this->source->isReadable();
    }

    public function close(): void
    {
        $this->source->close();
    }

    public function isClosed(): bool
    {
        return $this->source->isClosed();
    }
}
