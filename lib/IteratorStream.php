<?php

namespace Amp\ByteStream;

use Amp\Coroutine;
use Amp\Deferred;
use Amp\Iterator;
use Amp\Promise;
use Amp\Success;

/**
 * Creates a buffered message from an Iterator. The message can be consumed in chunks using the read() API or it may be
 * buffered and accessed in its entirety by waiting for the promise to resolve.
 *
 * Buffering Example:
 *
 * $stream = new IteratorStream($iterator); // $iterator is an instance of \Amp\Iterator emitting only strings.
 * $content = yield $stream;
 *
 * Streaming Example:
 *
 * $stream = new IteratorStream($iterator); // $iterator is an instance of \Amp\Iterator emitting only strings.
 *
 * while (($chunk = yield $stream->read()) !== null) {
 *     // Immediately use $chunk, reducing memory consumption since the entire message is never buffered.
 * }
 */
class IteratorStream implements InputStream, Promise {
    /** @var \Amp\Iterator|null */
    private $iterator;

    /** @var string */
    private $buffer = "";

    /** @var \Amp\Deferred|null */
    private $pendingRead;

    /** @var \Amp\Coroutine */
    private $coroutine;

    /**
     * @param \Amp\Iterator $iterator An iterator that only emits strings.
     */
    public function __construct(Iterator $iterator) {
        $this->iterator = $iterator;
        $this->coroutine = new Coroutine($this->iterate());
    }

    private function iterate(): \Generator {
        while ($this->iterator !== null && (yield $this->iterator->advance())) {
            $this->buffer .= $this->iterator->getCurrent();
            if ($this->pendingRead) {
                $deferred = $this->pendingRead;
                $this->pendingRead = null;
                $buffer = $this->buffer;
                $this->buffer = "";
                $deferred->resolve($buffer);
            }
        }

        $this->iterator = null;

        if ($this->pendingRead) {
            $deferred = $this->pendingRead;
            $this->pendingRead = null;
            $buffer = $this->buffer;
            $this->buffer = "";
            $deferred->resolve($buffer);
        }

        return $this->buffer;
    }

    public function read(): Promise {
        if ($this->buffer !== "") {
            $buffer = $this->buffer;
            $this->buffer = "";
            return new Success($buffer);
        }

        if ($this->iterator === null) {
            return new Success;
        }

        $this->pendingRead = new Deferred;
        return $this->pendingRead->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function onResolve(callable $onResolved) {
        $this->coroutine->onResolve($onResolved);
    }

    public function close() {
        $this->iterator = null;
    }
}
