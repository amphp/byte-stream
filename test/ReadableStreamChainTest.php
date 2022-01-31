<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline\Pipeline;
use function Amp\delay;

final class ReadableStreamChainTest extends AsyncTestCase
{
    public function test(): void
    {
        $stream = new ReadableStreamChain(
            $this->createStream(["abc", "def", "hi"]),
            new ReadableBuffer,
            $this->createStream(["kak"])
        );

        self::assertSame("abcdefhikak", buffer($stream));
    }

    private function createStream(array $chunks): ReadableStream
    {
        return new ReadableIterableStream(Pipeline::fromIterable($chunks)->tap(fn () => delay(0.01)));
    }
}
