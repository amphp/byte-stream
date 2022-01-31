<?php

namespace Amp\ByteStream;

use Amp\Cancellation;

final class BufferedReader
{
    private string $buffer = '';

    private bool $pending = false;

    public function __construct(
        private ReadableStream $stream,
    ) {
    }

    /**
     * @template TString as string|null
     *
     * @param \Closure():TString $read
     */
    private function guard(\Closure $read): mixed
    {
        if ($this->pending) {
            throw new PendingReadError();
        }

        $this->pending = true;

        try {
            return $read();
        } finally {
            $this->pending = false;
        }
    }

    public function emptyBuffer(): string
    {
        return $this->guard(function (): string {
            $buffer = $this->buffer;
            $this->buffer = '';
            return $buffer;
        });
    }

    /**
     * @throws StreamException If the implementation of {@see ReadableStream::read()} of the instance given the
     * constructor can throw.
     *
     * @see ReadableStream::read() Identical to this method, returning data from the internal buffer first.
     */
    public function read(?Cancellation $cancellation = null): ?string
    {
        return $this->guard(function () use ($cancellation): ?string {
            if ($this->buffer !== '') {
                $buffer = $this->buffer;
                $this->buffer = '';
                return $buffer;
            }

            return $this->stream->read($cancellation);
        });
    }

    /**
     * @param positive-int $length The number of bytes to read from the stream. Fewer bytes may be returned if the
     * stream closes before the given number of bytes can be read.
     *
     * @throws StreamException If the implementation of {@see ReadableStream::read()} of the instance given the
     * constructor can throw.
     */
    public function readLength(int $length, ?Cancellation $cancellation = null): string
    {
        if ($length <= 0) {
            throw new \ValueError('The number of bytes to read must be a positive integer');
        }

        return $this->guard(function () use ($length, $cancellation): string {
            while (\strlen($this->buffer) < $length) {
                $chunk = $this->stream->read($cancellation);
                if ($chunk === null) {
                    $buffer = $this->buffer;
                    $this->buffer = '';
                    return $buffer;
                }
                $this->buffer .= $chunk;
            }

            $buffer = \substr($this->buffer, 0, $length);
            $this->buffer = \substr($this->buffer, $length);
            return $buffer;
        });
    }

    /**
     * @param non-empty-string $suffix Read from the stream until the given suffix is found in the stream, at which
     * point all bytes up to and including the suffix will be returned. If the stream closes before the suffix is found,
     * the bytes read up to that point will be returned.
     *
     * @throws StreamException If the implementation of {@see ReadableStream::read()} of the instance given the
     * constructor can throw.
     */
    public function readUntil(string $suffix, ?Cancellation $cancellation = null): string
    {
        $length = \strlen($suffix);

        if (!$length) {
            throw new \ValueError('The suffix must be a non-empty string');
        }

        return $this->guard(function () use ($suffix, $length, $cancellation): string {
            while (($position = \strpos($this->buffer, $suffix)) === false) {
                $chunk = $this->stream->read($cancellation);
                if ($chunk === null) {
                    $buffer = $this->buffer;
                    $this->buffer = '';
                    return $buffer;
                }
                $this->buffer .= $chunk;
            }

            /** @psalm-suppress PossiblyUndefinedVariable */
            $position += $length;
            $buffer = \substr($this->buffer, 0, $position);
            $this->buffer = \substr($this->buffer, $position);
            return $buffer;
        });
    }

    /**
     * @see buffer()
     *
     * @throws BufferException If the $limit given is exceeded.
     * @throws StreamException If the implementation of {@see ReadableStream::read()} of the instance given the
     * constructor can throw.
     */
    public function bufferAll(?Cancellation $cancellation = null, int $limit = \PHP_INT_MAX): string
    {
        return $this->guard(function () use ($cancellation, $limit): string {
            $length = \strlen($this->buffer);
            $chunks = $this->buffer === '' ? [] : [$this->buffer];
            $this->buffer = '';

            while (null !== $chunk = $this->stream->read($cancellation)) {
                $chunks[] = $chunk;
                $length += \strlen($chunk);
                if ($length > $limit) {
                    throw new BufferException(\implode($chunks), "Max length of $limit bytes exceeded");
                }

                unset($chunk); // free memory
            }

            return \implode($chunks);
        });
    }
}
