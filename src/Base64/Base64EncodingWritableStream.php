<?php

namespace Amp\ByteStream\Base64;

use Amp\ByteStream\WritableStream;

final class Base64EncodingWritableStream implements WritableStream
{
    /** @var WritableStream */
    private WritableStream $destination;

    /** @var string */
    private string $buffer = '';

    public function __construct(WritableStream $destination)
    {
        $this->destination = $destination;
    }

    public function write(string $bytes): void
    {
        $this->buffer .= $bytes;

        $length = \strlen($this->buffer);
        $chunk = \base64_encode(\substr($this->buffer, 0, $length - $length % 3));
        $this->buffer = \substr($this->buffer, $length - $length % 3);

        $this->destination->write($chunk);
    }

    public function end(string $bytes = ""): void
    {
        $chunk = \base64_encode($this->buffer . $bytes);
        $this->buffer = '';

        $this->destination->end($chunk);
    }

    public function isWritable(): bool
    {
        return $this->destination->isWritable();
    }
}
