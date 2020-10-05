<?php

namespace Amp\ByteStream\Base64;

use Amp\ByteStream\OutputStream;
use Amp\Promise;

final class Base64EncodingOutputStream implements OutputStream
{
    /** @var OutputStream */
    private OutputStream $destination;

    /** @var string */
    private string $buffer = '';

    public function __construct(OutputStream $destination)
    {
        $this->destination = $destination;
    }

    public function write(string $data): void
    {
        $this->buffer .= $data;

        $length = \strlen($this->buffer);
        $chunk = \base64_encode(\substr($this->buffer, 0, $length - $length % 3));
        $this->buffer = \substr($this->buffer, $length - $length % 3);

        $this->destination->write($chunk);
    }

    public function end(string $finalData = ""): void
    {
        $chunk = \base64_encode($this->buffer . $finalData);
        $this->buffer = '';

        $this->destination->end($chunk);
    }
}
