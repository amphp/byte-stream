<?php

namespace Amp\ByteStream;

use Amp\{ Deferred, Failure, Promise, Stream };

class Message implements ReadableStream {
    /** @var \Amp\ByteStream\Buffer */
    private $buffer;

    /** @var \SplQueue */
    private $reads;

    /** @var bool */
    private $closed = false;

    /**
     * @param \Amp\Stream $stream Stream emitting only strings.
     */
    public function __construct(Stream $stream) {
        $this->buffer = new Buffer;
        $this->reads = new \SplQueue;

        $stream->onEmit(function (string $data) {
            $this->buffer->push($data);
            $this->checkPendingReads();
        });

        $stream->onResolve(function () {
            $this->close();
        });
    }

    /**
     * Closes the stream and fails any pending reads or writes.
     */
    private function close() {
        $this->closed = true;

        while (!$this->reads->isEmpty()) {
            /** @var \Amp\Deferred $deferred */
            list($bytes, $delimiter, $deferred) = $this->reads->shift();
            if ($delimiter === null && $bytes > 0) {
                $exception = new ClosedException("The stream ended before the read request could be satisfied");
                $deferred->fail($exception);
                while (!$this->reads->isEmpty()) { // If prior read failed, fail all subsequent reads.
                    list( , , $deferred) = $this->reads->shift();
                    $deferred->fail($exception);
                }
                return;
            } else {
                $deferred->resolve($this->buffer->drain()); // Resolve unbounded reads with remaining buffer.
            }
        }
    }

    /**
     * Determines if the stream is readable.
     *
     * @return bool
     */
    public function isReadable(): bool {
        return !$this->closed || !$this->buffer->isEmpty();
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $bytes = null): Promise {
        return $this->fetch($bytes);
    }

    /**
     * {@inheritdoc}
     */
    public function readTo(string $delimiter, int $limit = null): Promise {
        return $this->fetch($limit, $delimiter);
    }

    /**
     * {@inheritdoc}
     */
    public function readAll(): Promise {
        if (!$this->isReadable()) {
            return new Failure(new StreamException("The stream is no longer readable"));
        }

        $this->reads->push([0, null, $deferred = new Deferred]);
        $this->checkPendingReads();

        return $deferred->promise();
    }

    private function fetch(int $bytes = null, string $delimiter = null): Promise {
        if ($bytes !== null && $bytes <= 0) {
            throw new \Error("The number of bytes to read should be a positive integer or null");
        }

        if (!$this->isReadable()) {
            return new Failure(new StreamException("The stream is no longer readable"));
        }

        $deferred = new Deferred;
        $this->reads->push([$bytes, $delimiter, $deferred]);
        $this->checkPendingReads();

        return $deferred->promise();
    }

    private function checkPendingReads() {
        while (!$this->reads->isEmpty()) {
            /**
             * @var int|null $bytes
             * @var string|null $delimiter
             * @var \Amp\Deferred $deferred
             */
            list($bytes, $delimiter, $deferred) = $this->reads->shift();

            if ($delimiter !== null && ($position = $this->buffer->search($delimiter)) !== false) {
                $length = $position + \strlen($delimiter);

                if ($bytes === null || $length < $bytes) {
                    $deferred->resolve($this->buffer->shift($length));
                    continue;
                }
            }

            if ($bytes > 0 && $this->buffer->getLength() >= $bytes) {
                $deferred->resolve($this->buffer->shift($bytes));
                continue;
            }

            if ($delimiter === null && $bytes === null && !$this->buffer->isEmpty()) {
                $deferred->resolve($this->buffer->drain());
                continue;
            }

            $this->reads->unshift([$bytes, $delimiter, $deferred]);
            return;
        }
    }
}
