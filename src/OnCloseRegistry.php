<?php

namespace Amp\ByteStream;

use Revolt\EventLoop;

final class OnCloseRegistry
{
    /** @var list<\Closure():void>|null */
    private ?array $onClose = [];

    public function __destruct()
    {
        if ($this->onClose !== null) {
            $this->call();
        }
    }

    /**
     * @param \Closure():void $onClose
     */
    public function register(\Closure $onClose): void
    {
        if ($this->onClose === null) {
            EventLoop::queue($onClose);
            return;
        }

        $this->onClose[] = $onClose;
    }

    public function call(): void
    {
        if ($this->onClose === null) {
            return;
        }

        foreach ($this->onClose as $onClose) {
            EventLoop::queue($onClose);
        }

        $this->onClose = null;
    }
}
