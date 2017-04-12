<?php

namespace Amp\ByteStream;

use Amp\{ Emitter, Failure, Promise, Success };

/**
 * Serves as buffer that implements the stream interface, allowing consumers to be notified when data is available in
 * the buffer. This class by itself is not particularly useful, but it can be extended to add functionality upon reading
 * or writing, as well as acting as an example of how stream classes can be implemented.
 */
class MemoryStream extends Message implements DuplexStream {
    /** @var \Amp\Emitter */
    private $emitter;

    private $writable = true;

    /**
     * @param string $data
     */
    public function __construct(string $data = '') {
        $this->emitter = new Emitter;
        parent::__construct($this->emitter->stream());
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

        $this->emitter->emit($data);

        return new Success(\strlen($data));
    }
}
