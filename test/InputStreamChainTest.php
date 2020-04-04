<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\Iterator;
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

        self::assertSame("abcdefhikak", yield buffer($stream));
    }

    private function createStream(array $chunks): InputStream
    {
        return new IteratorStream(Iterator\fromIterable($chunks, 1));
    }
}
