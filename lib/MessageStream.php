<?php

namespace Amp\ByteStream;

use Amp\{ Coroutine, Message, Promise };

class MessageStream implements ReadableStream {
    /** @var \Amp\ByteStream\MemoryStream */
    private $stream;

    /**
     * @param \Amp\Message $message
     */
    public function __construct(Message $message) {
        $this->stream = new MemoryStream;

        $coroutine = new Coroutine($this->pipe($message));
        $coroutine->onResolve(function () {
            $this->stream->end();
        });
    }

    private function pipe(Message $message): \Generator {
        while (yield $message->advance()) {
            $this->stream->write($message->getCurrent());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(): bool {
        return $this->stream->isReadable();
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $bytes = null): Promise {
        return $this->stream->read($bytes);
    }

    /**
     * {@inheritdoc}
     */
    public function readTo(string $delimiter, int $limit = null): Promise {
        return $this->stream->readTo($delimiter, $limit);
    }

    /**
     * {@inheritdoc}
     */
    public function close() {
        $this->stream->close();
    }
}
