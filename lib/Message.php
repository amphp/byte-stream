<?php

namespace Amp\ByteStream;

use Amp\Coroutine;
use Amp\Deferred;
use Amp\Failure;
use Amp\Promise;
use Amp\Success;

/**
 * Creates a buffered message from an InputStream. The message can be consumed in chunks using the read() API or it may
 * be buffered and accessed in its entirety by waiting for the promise to resolve.
 *
 * Other implementations may extend this class to add custom properties such as a `isBinary()` flag for WebSocket
 * messages.
 *
 * Buffering Example:
 *
 * $stream = new Message($inputStream);
 * $content = $stream->buffer();
 *
 * Streaming Example:
 *
 * $stream = new Message($inputStream);
 *
 * while (($chunk = yield $stream->read()) !== null) {
 *     // Immediately use $chunk, reducing memory consumption since the entire message is never buffered.
 * }
 */
class Message implements InputStream {
    /** @var InputStream */
    private $source;

    /** @var \Amp\Deferred|null */
    private $pendingRead;

    /** @var bool True if onResolve() has been called. */
    private $buffering = false;

    /** @var \Throwable Used to fail future reads on failure. */
    private $error;

    /**
     * @param InputStream $source An iterator that only emits strings.
     */
    public function __construct(InputStream $source) {
        $this->source = $source;
    }

    /** @inheritdoc */
    final public function read(): ?string {
        if ($this->pendingRead) {
            throw new PendingReadError;
        }

        if ($this->error) {
            throw $this->error;
        }

        return $this->source->read();
    }

    /** @inheritdoc */
    final public function buffer() {
        $this->buffering = true;

        $buffer = "";

        while (null !== $chunk = $this->source->read()) {
            $buffer .= $chunk;
        }

        return $buffer;
    }

    /**
     * Exposes the source input stream.
     *
     * This might be required to resolve a promise with an InputStream, because promises in Amp can't be resolved with
     * other promises.
     *
     * @return InputStream
     */
    final public function getInputStream(): InputStream {
        return $this->source;
    }
}
