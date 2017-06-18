<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Loop;
use PHPUnit\Framework\TestCase;

class ResourceStreamTest extends TestCase {
    public function testLargePayloads() {
        Loop::run(function () {
            $domain = \stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX;
            list($left, $right) = @\stream_socket_pair($domain, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

            $a = new ResourceOutputStream($left);
            $b = new ResourceInputStream($right);

            $length = 1 * 1024 * 1024; // 1M

            $message = "";
            for ($i = 0; $i < $length; $i++) {
                $message .= \chr(\mt_rand(33, 125));
            }

            $a->end($message)->onResolve(function () use (&$a, &$left) {
                // Let GC close resource
                $a = null;
                $left = null;
            });

            $received = "";
            while (null !== $chunk = yield $b->read()) {
                $received .= $chunk;
            }

            $this->assertSame($message, $received);
        });
    }
}
