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
    private $readable = true;
    
    /** @var bool */
    private $writable = true;
    
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
        return $this->readable;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isWritable(): bool {
        return $this->writable;
    }
    
    /**
     * {@inheritdoc}
     */
    public function close() {
        $this->readable = false;
        $this->writable = false;
        
        if (!$this->reads->isEmpty()) {
            $exception = new ClosedException("The stream was unexpectedly closed");
            do {
                /** @var \Amp\Deferred $deferred */
                list( , , $deferred) = $this->reads->shift();
                $deferred->fail($exception);
            } while (!$this->reads->isEmpty());
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

    private function fetch(int $bytes = null, string $delimiter = null): Promise {
        if ($bytes !== null && $bytes <= 0) {
            throw new \Error("The number of bytes to read should be a positive integer or null");
        }
    
        if (!$this->readable) {
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
            
            if ($bytes !== null && $this->buffer->getLength() >= $bytes) {
                $deferred->resolve($this->buffer->shift($bytes));
                continue;
            }
    
            if ($bytes === null) {
                $deferred->resolve($this->buffer->drain());
                continue;
            }
    
            $this->reads->unshift([$bytes, $delimiter, $deferred]);
            return;
        }
        
        if (!$this->writable && $this->buffer->isEmpty()) {
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
        if (!$this->writable) {
            return new Failure(new StreamException("The stream is not writable"));
        }
        
        if ($end) {
            $this->writable = false;
        }
        
        $this->buffer->push($data);
        $this->checkPendingReads();
        
        return new Success(\strlen($data));
    }
}
