<?php

namespace Amp\ByteStream\Base64;

use Amp\ByteStream\StreamException;
use Amp\ByteStream\WritableStream;

final class Base64DecodingWritableStream implements WritableStream
{
    /** @var WritableStream */
    private WritableStream $destination;

    /** @var string */
    private string $buffer = '';

    /** @var int */
    private int $offset = 0;

    public function __construct(WritableStream $destination)
    {
        $this->destination = $destination;
    }

    public function write(string $bytes): void
    {
        $this->buffer .= $bytes;

        $length = \strlen($this->buffer);
        $chunk = \base64_decode(\substr($this->buffer, 0, $length - $length % 4), true);
        if ($chunk === false) {
            throw new StreamException('Invalid base64 near offset ' . $this->offset);
        }

        $this->offset += $length - $length % 4;
        $this->buffer = \substr($this->buffer, $length - $length % 4);

        $this->destination->write($chunk);
    }

    public function end(): void
    {
        $this->offset += \strlen($this->buffer);

        $chunk = \base64_decode($this->buffer, true);
        if ($chunk === false) {
            throw new StreamException('Invalid base64 near offset ' . $this->offset);
        }

        $this->buffer = '';

        $this->destination->write($chunk);
        $this->destination->end();
    }

    public function isWritable(): bool
    {
        return $this->destination->isWritable();
    }

    public function close(): void
    {
        $this->destination->close();
    }

    public function isClosed(): bool
    {
        return $this->destination->isClosed();
    }
}
