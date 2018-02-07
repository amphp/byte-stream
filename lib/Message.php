<?php

namespace Amp\ByteStream;

use Amp\Promise;
use function Amp\asyncCall;
use function Amp\call;

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
 * $content = yield $stream;
 *
 * Streaming Example:
 *
 * $stream = new Message($inputStream);
 *
 * while (($chunk = yield $stream->read()) !== null) {
 *     // Immediately use $chunk, reducing memory consumption since the entire message is never buffered.
 * }
 */
class Message implements InputStream, Promise {
    /** @var InputStream */
    private $stream;

    /** @var Promise|null */
    private $promise;

    /** @var Promise|null */
    private $lastRead;

    /**
     * @param InputStream $stream Any input stream.
     */
    public function __construct(InputStream $stream) {
        $this->stream = $stream;
    }

    public function __destruct() {
        if (!$this->promise) {
            if ($this->lastRead) {
                asyncCall(function () {
                    try {
                        yield $this->lastRead;
                    } catch (\Throwable $e) {
                        // ignore
                    }

                    yield discard($this->stream);
                });
            } else {
                Promise\rethrow(discard($this->stream));
            }
        }
    }

    /** @inheritdoc */
    final public function read(): Promise {
        if ($this->promise) {
            throw new PendingReadError('Cannot stream message data once a buffered message has been requested');
        }

        return $this->lastRead = $this->stream->read();
    }

    /**
     * Buffers the entire message and resolves the returned promise then.
     *
     * @param int|null $sizeLimit Size limit in bytes or `null` for no limit.
     *
     * @return Promise<string> Resolves with the entire message contents.
     *
     * @see buffer()
     */
    final public function buffer(int $sizeLimit = null): Promise {
        if ($this->promise) {
            return $this->promise;
        }

        if (!$this->lastRead) {
            return $this->promise = buffer($this->stream, $sizeLimit);
        }

        return call(function () use ($sizeLimit) {
            yield $this->lastRead; // discarded

            return buffer($this->stream, $sizeLimit);
        });
    }

    /** @inheritdoc */
    final public function onResolve(callable $onResolved) {
        \trigger_error('Message::onResolve() and yielding Message objects directly is now deprecated and will be removed in a future version. Use Message::buffer() instead.', \E_USER_DEPRECATED);

        $this->buffer()->onResolve($onResolved);
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
        return $this->stream;
    }
}
