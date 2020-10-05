<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\Pipeline;
use Amp\PHPUnit\AsyncTestCase;

class InputStreamChainTest extends AsyncTestCase
{
    public function test()
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
