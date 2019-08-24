<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\Iterator;
use Amp\PHPUnit\TestCase;
use function Amp\Promise\wait;

class InputStreamChainTest extends TestCase
{
    public function test()
    {
        $stream = new InputStreamChain(
            $this->createStream(["abc", "def", "hi"]),
            new InMemoryStream,
            $this->createStream(["kak"])
        );

        self::assertSame("abcdefhikak", wait(buffer($stream)));
    }

    private function createStream(array $chunks): InputStream
    {
        return new IteratorStream(Iterator\fromIterable($chunks, 1));
    }
}
