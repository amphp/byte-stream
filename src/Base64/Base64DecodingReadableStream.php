<?php

namespace Amp\ByteStream\Base64;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;

final class Base64DecodingReadableStream implements ReadableStream
{
    private ?ReadableStream $source;

    private string $buffer = '';

    public function __construct(ReadableStream $source)
    {
        $this->source = $source;
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        if ($this->source === null) {
            throw new StreamException('Failed to read stream chunk due to invalid base64 data');
        }

        $chunk = $this->source->read($cancellation);
        if ($chunk === null) {
            if ($this->buffer === '') {
                return null;
            }

            $chunk = \base64_decode($this->buffer, true);
            $this->buffer = '';

            if ($chunk === false) {
                $this->source = null;

                throw new StreamException('Failed to read stream chunk due to invalid base64 data');
            }

            return $chunk;
        }

        $this->buffer .= $chunk;

        $length = \strlen($this->buffer);
        $chunk = \base64_decode(\substr($this->buffer, 0, $length - $length % 4), true);

        if ($chunk === false) {
            $this->source = null;
            $this->buffer = '';

            throw new StreamException('Failed to read stream chunk due to invalid base64 data');
        }

        $this->buffer = \substr($this->buffer, $length - $length % 4);

        return $chunk;
    }

    public function isReadable(): bool
    {
        return (bool) $this->source?->isReadable();
    }

    public function close(): void
    {
        $this->source?->close();
    }

    public function isClosed(): bool
    {
        return $this->source?->isClosed() ?? true;
    }
}
