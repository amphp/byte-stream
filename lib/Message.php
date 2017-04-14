<?php

namespace Amp\ByteStream;

use Amp\{ Deferred, Promise, Stream, StreamIterator, Success };

/**
 * Creates a buffered message from a Stream. The message can be consumed in chunks using the advance() and getChunk()
 * methods or it may be buffered and accessed in its entirety by waiting for the promise to resolve.
 *
 * Buffering Example:
 *
 * $message = new Message($stream); // $stream is an instance of \Amp\Stream emitting only strings.
 * $content = yield $message;
 *
 * Streaming Example:
 *
 * $message = new Message($stream); // $stream is a Stream emitting only strings.
 *
 * while (yield $message->advance()) {
 *     $chunk = $message->getChunk();
 *     // Immediately use $chunk, reducing memory consumption since the entire message is never buffered.
 * }
 */
class Message implements ReadableStream, Promise {
    const LISTENING = 0;
    const BUFFERING = 1;
    const WAITING = 2;
    const COMPLETE = 4;

    /** @var \Amp\StreamIterator|null */
    private $iterator;

    /** @var int */
    private $status = self::LISTENING;

    /** @var mixed Final emitted chunk. */
    private $result;

    /** @var \Amp\Deferred */
    private $deferred;

    /**
     * @param \Amp\Stream $stream Stream that only emits strings.
     */
    public function __construct(Stream $stream) {
        $this->iterator = new StreamIterator($stream);
        $this->deferred = new Deferred;

        $stream->onResolve(function ($exception, $value) {
            if ($exception) {
                $this->deferred->fail($exception);
                return;
            }

            $result = \implode($this->iterator->drain());
            $this->iterator = null;
            $this->status = \strlen($result) ? self::BUFFERING : self::WAITING;
            $this->result = $result;
            $this->deferred->resolve($result);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function advance(): Promise {
        if ($this->iterator) {
            return $this->iterator->advance();
        }

        switch ($this->status) {
            case self::BUFFERING:
                $this->status = self::WAITING;
                return new Success(true);

            case self::WAITING:
                $this->status = self::COMPLETE;
                return new Success(false);

            default:
                throw new \Error("The stream has resolved");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getChunk(): string {
        if ($this->iterator) {
            return $this->iterator->getCurrent();
        }

        switch ($this->status) {
            case self::COMPLETE:
                throw new \Error("The stream has resolved");

            default:
                return $this->result;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onResolve(callable $onResolved) {
        $this->deferred->promise()->onResolve($onResolved);
    }
}
