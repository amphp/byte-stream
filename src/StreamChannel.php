<?php

namespace Amp\ByteStream;

use Amp\ByteStream\Internal\ChannelParser;
use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Pipeline;
use Amp\Serialization\Serializer;
use Amp\Sync\Channel;
use Amp\Sync\ChannelException;

/**
 * An asynchronous channel for sending data between threads and processes.
 *
 * Supports full duplex read and write.
 *
 * @template TReceive
 * @template TSend
 * @template-implements Channel<TReceive, TSend>
 */
final class StreamChannel implements Channel
{
    private ReadableStream $read;

    private WritableStream $write;

    private ChannelParser $parser;

    /** @var ConcurrentIterator<TReceive> */
    private ConcurrentIterator $iterator;

    private int $pendingReceives = 0;
    private ?DeferredFuture $readBarrier = null;

    /**
     * Creates a new channel from the given stream objects. Note that $read and $write can be the same object.
     */
    public function __construct(ReadableStream $read, WritableStream $write, ?Serializer $serializer = null)
    {
        $this->read = $read;
        $this->write = $write;

        $readBarrier = &$this->readBarrier;
        $pendingReceives = &$this->pendingReceives;

        $received = new \SplQueue();
        $this->parser = $parser = new ChannelParser(\Closure::fromCallable([$received, 'push']), $serializer);

        $this->iterator = Pipeline::fromIterable(static function () use (
            $read,
            $received,
            $parser,
            &$readBarrier,
            &$pendingReceives,
        ): \Generator {
            while (true) {
                if ($pendingReceives === 0) {
                    $readBarrier = new DeferredFuture();
                    $readBarrier->getFuture()->await();
                }

                try {
                    $chunk = $read->read();
                } catch (StreamException $exception) {
                    throw new ChannelException(
                        "Reading from the channel failed. Did the context die?",
                        0,
                        $exception,
                    );
                }

                if ($chunk === null) {
                    throw new ChannelException("The channel closed while waiting to receive the next value");
                }

                $parser->push($chunk);

                while (!$received->isEmpty()) {
                    $pendingReceives--;
                    yield $received->shift();
                }
            }
        })->getIterator();
    }

    public function __destruct()
    {
        $this->close();
    }

    public function send(mixed $data): void
    {
        $data = $this->parser->encode($data);

        try {
            $this->write->write($data);
        } catch (\Throwable $exception) {
            throw new ChannelException("Sending on the channel failed. Did the context die?", 0, $exception);
        }
    }

    public function receive(?Cancellation $cancellation = null): mixed
    {
        if (++$this->pendingReceives === 1) {
            $this->readBarrier?->complete();
            $this->readBarrier = null;
        }

        if (!$this->iterator->continue($cancellation)) {
            throw new ChannelException("The channel closed while waiting to receive the next value");
        }

        return $this->iterator->getValue();
    }

    public function isClosed(): bool
    {
        return $this->read->isClosed() || $this->write->isClosed();
    }

    /**
     * Closes the read and write resource streams.
     */
    public function close(): void
    {
        $this->read->close();
        $this->write->close();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->read->onClose($onClose);
    }
}
