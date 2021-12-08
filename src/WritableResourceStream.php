<?php

namespace Amp\ByteStream;

use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

/**
 * Output stream abstraction for PHP's stream resources.
 */
final class WritableResourceStream implements WritableStream, ClosableStream, ResourceStream
{
    private const MAX_CONSECUTIVE_EMPTY_WRITES = 3;
    private const LARGE_CHUNK_SIZE = 128 * 1024;

    /** @var resource|null */
    private $resource;

    private string $callbackId;

    /** @var \SplQueue<array{string, int, Suspension|null, bool}> */
    private \SplQueue $writes;

    private bool $writable = true;

    private ?int $chunkSize = null;

    /**
     * @param resource $stream Stream resource.
     * @param positive-int|null $chunkSize Chunk size per `fwrite()` operation.
     */
    public function __construct($stream, ?int $chunkSize = null)
    {
        if (!\is_resource($stream) || \get_resource_type($stream) !== 'stream') {
            throw new \Error("Expected a valid stream");
        }

        $meta = \stream_get_meta_data($stream);

        if (\str_contains($meta["mode"], "r") && !\str_contains($meta["mode"], "+")) {
            throw new \Error("Expected a writable stream");
        }

        if ($chunkSize !== null && $chunkSize <= 0) {
            throw new \ValueError('The chunk length must be a positive integer');
        }

        \stream_set_blocking($stream, false);
        \stream_set_write_buffer($stream, 0);

        $this->resource = $stream;
        $this->chunkSize = &$chunkSize;

        $writes = $this->writes = new \SplQueue;
        $writable = &$this->writable;
        $resource = &$this->resource;

        $this->callbackId = EventLoop::disable(EventLoop::onWritable($stream, static function ($callbackId, $stream) use (
            $writes,
            &$chunkSize,
            &$writable,
            &$resource
        ): void {
            static $emptyWrites = 0;

            $end = false;
            $suspension = null;

            try {
                while (!$writes->isEmpty()) {
                    /** @var Suspension|null $suspension */
                    [$data, $previous, $suspension, $end] = $writes->shift();
                    $length = \strlen($data);

                    if ($length === 0) {
                        $suspension?->resume();
                        continue;
                    }

                    if (!\is_resource($stream)) {
                        throw new ClosedException("The stream was closed by the peer");
                    }

                    // Error reporting suppressed since fwrite() emits E_WARNING if the pipe is broken or the buffer is full.
                    // Use conditional, because PHP doesn't like getting null passed
                    if ($chunkSize) {
                        $written = @\fwrite($stream, $data, $chunkSize);
                    } else {
                        $written = @\fwrite($stream, $data);
                    }

                    if ($written === false) {
                        $message = "Failed to write to stream";
                        if ($error = \error_get_last()) {
                            $message .= \sprintf("; %s", $error["message"]);
                        }
                        throw new StreamException($message);
                    }

                    // Broken pipes between processes on macOS/FreeBSD do not detect EOF properly.
                    if ($written === 0) {
                        if ($emptyWrites++ > self::MAX_CONSECUTIVE_EMPTY_WRITES) {
                            $message = "Failed to write to stream after multiple attempts";
                            if ($error = \error_get_last()) {
                                $message .= \sprintf("; %s", $error["message"]);
                            }
                            throw new StreamException($message);
                        }

                        $writes->unshift([$data, $previous, $suspension, $end]);
                        $end = false;
                        return;
                    }

                    $emptyWrites = 0;

                    if ($length > $written) {
                        $data = \substr($data, $written);
                        $writes->unshift([$data, $written + $previous, $suspension, $end]);
                        $end = false;
                        return;
                    }

                    $suspension?->resume();
                }
            } catch (\Throwable $exception) {
                $writable = false;
                $end = true;

                $suspension?->throw($exception);
                while (!$writes->isEmpty()) {
                    [, , $suspension] = $writes->shift();
                    $suspension?->throw($exception);
                }

                EventLoop::cancel($callbackId);
            } finally {
                if ($writes->isEmpty()) {
                    EventLoop::disable($callbackId);
                }

                if ($end && \is_resource($resource)) {
                    $meta = \stream_get_meta_data($resource);
                    if (\str_contains($meta["mode"], "+")) {
                        \stream_socket_shutdown($resource, \STREAM_SHUT_WR);
                    } else {
                        \fclose($resource);
                    }
                    $resource = null;
                }
            }
        }));
    }

    /**
     * Writes data to the stream.
     *
     * @param string $bytes Bytes to write.
     *
     * @throws ClosedException If the stream has already been closed.
     */
    public function write(string $bytes): void
    {
        $this->send($bytes, false);
    }

    /**
     * Closes the stream after all pending writes have been completed. Optionally writes a final data chunk before.
     *
     * @param string $bytes Bytes to write.
     */
    public function end(string $bytes = ''): void
    {
        $this->send($bytes, true);
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * Closes the stream forcefully. Multiple `close()` calls are ignored.
     */
    public function close(): void
    {
        if (\is_resource($this->resource) && \get_resource_type($this->resource) === 'stream') {
            // Error suppression, as resource might already be closed
            $meta = \stream_get_meta_data($this->resource);

            if (\str_contains($meta["mode"], "+")) {
                \stream_socket_shutdown($this->resource, \STREAM_SHUT_WR);
            } else {
                /** @psalm-suppress InvalidPropertyAssignmentValue psalm reports this as closed-resource */
                \fclose($this->resource);
            }
        }

        $this->free();
    }

    public function isClosed(): bool
    {
        return $this->resource === null;
    }

    /**
     * @return resource|object|null Stream resource or null if end() has been called or the stream closed.
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param positive-int $chunkSize
     */
    public function setChunkSize(int $chunkSize): void
    {
        if ($chunkSize <= 0) {
            throw new \ValueError('The chunk length must be a positive integer');
        }

        $this->chunkSize = $chunkSize;
    }

    public function __destruct()
    {
        $this->free();
    }

    private function send(string $data, bool $end): void
    {
        if (!$this->writable) {
            throw new ClosedException("The stream is not writable");
        }

        $length = \strlen($data);
        $written = 0;

        if ($end) {
            $this->writable = false;
        }

        if ($this->writes->isEmpty()) {
            if ($length === 0) {
                if ($end) {
                    $this->close();
                }
                return;
            }

            if (!\is_resource($this->resource)) {
                throw new ClosedException("The stream was closed by the peer");
            }

            // Error reporting suppressed since fwrite() emits E_WARNING if the pipe is broken or the buffer is full.
            // Use conditional, because PHP doesn't like getting null passed.
            if ($this->chunkSize) {
                $written = @\fwrite($this->resource, $data, $this->chunkSize);
            } else {
                $written = @\fwrite($this->resource, $data);
            }

            if ($written === false) {
                $message = "Failed to write to stream";
                if ($error = \error_get_last()) {
                    $message .= \sprintf("; %s", $error["message"]);
                }
                throw new StreamException($message);
            }

            if ($length === $written) {
                if ($end) {
                    $this->close();
                }
                return;
            }

            $data = \substr($data, $written);
        }

        if ($length - $written > self::LARGE_CHUNK_SIZE) {
            $chunks = \str_split($data, self::LARGE_CHUNK_SIZE);

            /** @var string $data */
            $data = \array_pop($chunks);

            foreach ($chunks as $chunk) {
                $this->writes->push([$chunk, $written, null, false]);
                $written += self::LARGE_CHUNK_SIZE;
            }
        }

        EventLoop::enable($this->callbackId);
        $this->writes->push([$data, $written, $suspension = EventLoop::createSuspension(), $end]);

        $suspension->suspend();
    }

    /**
     * References the writable watcher, so the loop keeps running in case there's a pending write.
     *
     * @see EventLoop::reference()
     */
    public function reference(): void
    {
        if (!$this->resource) {
            return;
        }

        EventLoop::reference($this->callbackId);
    }

    /**
     * Unreferences the writable watcher, so the loop doesn't keep running even if there are pending writes.
     *
     * @see EventLoop::unreference()
     */
    public function unreference(): void
    {
        if (!$this->resource) {
            return;
        }

        EventLoop::unreference($this->callbackId);
    }

    /**
     * Nulls reference to resource, marks stream unwritable, and fails any pending write.
     */
    private function free(): void
    {
        if ($this->resource === null) {
            return;
        }

        $this->resource = null;
        $this->writable = false;

        if (!$this->writes->isEmpty()) {
            $exception = new ClosedException("The socket was closed before writing completed");
            do {
                /** @var Suspension|null $suspension */
                [, , $suspension] = $this->writes->shift();
                $suspension?->throw($exception);
            } while (!$this->writes->isEmpty());
        }

        EventLoop::cancel($this->callbackId);
    }
}
