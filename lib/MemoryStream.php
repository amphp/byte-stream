<?php

namespace Amp\ByteStream;

use Amp\{ Deferred, Failure, Promise, Success };

/**
 * Serves as buffer that implements the stream interface, allowing consumers to be notified when data is available in
 * the buffer. This class by itself is not particularly useful, but it can be extended to add functionality upon reading
 * or writing, as well as acting as an example of how stream classes can be implemented.
 */
class MemoryStream implements DuplexStream {
    /** @var \Amp\ByteStream\Buffer */
    private $buffer;
    
    /** @var bool */
    private $closed = false;

    /** @var \SplQueue */
    private $reads;
    
    /**
     * @param string $data
     */
    public function __construct(string $data = '') {
        $this->buffer = new Buffer($data);
        $this->reads = new \SplQueue;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isReadable(): bool {
        return !$this->closed || !$this->buffer->isEmpty();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isWritable(): bool {
        return !$this->closed;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function close() {
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
            return new Failure(new StreamException("The stream is not readable"));
        }
        
        $deferred = new Deferred;
        $this->reads->push([$bytes, $delimiter, $deferred]);
        $this->checkPendingReads();
        
        return $deferred->promise();
    }

    /**
     * Returns bytes from the buffer based on the current length or current search byte.
     */
    private function checkPendingReads() {
        while (!$this->buffer->isEmpty() && !$this->reads->isEmpty()) {
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
            break;
        }
        
        if (!$this->isWritable()) {
            $this->close();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function write(string $data): Promise {
        return $this->send($data, false);
    }
    
    /**
     * {@inheritdoc}
     */
    public function end(string $data = ''): Promise {
        return $this->send($data, true);
    }
    
    /**
     * @param string $data
     * @param bool $end
     *
     * @return \Amp\Promise
     */
    protected function send(string $data, bool $end = false): Promise {
        if (!$this->isWritable()) {
            return new Failure(new StreamException("The stream is not writable"));
        }

        $this->buffer->push($data);
        $this->checkPendingReads();

        if ($end) {
            $this->close();
        }

        return new Success(\strlen($data));
    }
}
