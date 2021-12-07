<?php

namespace Amp\ByteStream\Base64;

use Amp\ByteStream\StreamException;
use Amp\ByteStream\WritableStream;
use Amp\Future;

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

    public function write(string $bytes): Future
    {
        $this->buffer .= $bytes;

        $length = \strlen($this->buffer);
        $chunk = \base64_decode(\substr($this->buffer, 0, $length - $length % 4), true);
        if ($chunk === false) {
            throw new StreamException('Invalid base64 near offset ' . $this->offset);
        }

        $this->offset += $length - $length % 4;
        $this->buffer = \substr($this->buffer, $length - $length % 4);

        return $this->destination->write($chunk);
    }

    public function end(string $bytes = ""): Future
    {
        $this->offset += \strlen($this->buffer);

        $chunk = \base64_decode($this->buffer . $bytes, true);
        if ($chunk === false) {
            throw new StreamException('Invalid base64 near offset ' . $this->offset);
        }

        $this->buffer = '';

        return $this->destination->end($chunk);
    }

    public function isWritable(): bool
    {
        return $this->destination->isWritable();
    }
}
