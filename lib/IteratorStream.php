<?php

namespace Amp\ByteStream;

use Amp\Deferred;
use Amp\Iterator;
use Amp\Promise;

class IteratorStream implements InputStream {
    private $iterator;

    public function __construct(Iterator $iterator) {
        $this->iterator = $iterator;
    }

    /** @inheritdoc */
    public function read(): Promise {
        $deferred = new Deferred;

        $this->iterator->advance()->onResolve(function ($error, $hasNextElement) use ($deferred) {
            if ($error) {
                $deferred->fail($error);
            } elseif ($hasNextElement) {
                $deferred->resolve($this->iterator->getCurrent());
            } else {
                $deferred->resolve(null);
            }
        });

        return $deferred->promise();
    }
}
