<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;

class ReadableStreamChainTest extends AsyncTestCase
{
    public function test(): void
    {
        $stream = new ReadableStreamChain(
            $this->createStream(["abc", "def", "hi"]),
            new InMemoryStream,
            $this->createStream(["kak"])
        );

        self::assertSame("abcdefhikak", buffer($stream));
    }

    private function createStream(array $chunks): ReadableStream
    {
        return new PipelineStream(Pipeline\fromIterable($chunks)->pipe(Pipeline\postpone(0.01)));
    }
}
