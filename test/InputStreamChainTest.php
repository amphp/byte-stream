<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;

class InputStreamChainTest extends AsyncTestCase
{
    public function test(): void
    {
        $stream = new InputStreamChain(
            $this->createStream(["abc", "def", "hi"]),
            new InMemoryStream,
            $this->createStream(["kak"])
        );

        self::assertSame("abcdefhikak", buffer($stream));
    }

    private function createStream(array $chunks): InputStream
    {
        return new PipelineStream(Pipeline\fromIterable($chunks, 1));
    }
}
