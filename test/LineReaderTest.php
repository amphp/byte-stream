<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\Iterator;
use Amp\PHPUnit\TestCase;
use function Amp\call;
use function Amp\Promise\wait;

class LineReaderTest extends TestCase
{
    public function testSingleLine(): void
    {
        $this->check(["abc"], ["abc"]);
    }

    public function testMultiLineLf(): void
    {
        $this->check(["abc\nef"], ["abc", "ef"]);
    }

    public function testMultiLineCrLf(): void
    {
        $this->check(["abc\r\nef"], ["abc", "ef"]);
    }

    public function testMultiLineEmptyNewlineStart(): void
    {
        $this->check(["\r\nabc\r\nef\r\n"], ["", "abc", "ef"]);
    }

    public function testMultiLineEmptyNewlineEnd(): void
    {
        $this->check(["abc\r\nef\r\n"], ["abc", "ef"]);
    }

    public function testMultiLineEmptyNewlineMiddle(): void
    {
        $this->check(["abc\r\n\r\nef\r\n"], ["abc", "", "ef"]);
    }

    public function testEmpty(): void
    {
        $this->check([], []);
    }

    public function testEmptyCrLf(): void
    {
        $this->check(["\r\n"], [""]);
    }

    public function testMultiLineSlow(): void
    {
        $this->check(["a", "bc", "\r", "\n\r\nef\r", "\n"], ["abc", "", "ef"]);
    }

    private function check(array $chunks, array $expectedLines): void
    {
        wait(call(static function () use ($chunks, $expectedLines) {
            $inputStream = new IteratorStream(Iterator\fromIterable($chunks));

            $reader = new LineReader($inputStream);
            $lines = [];

            while (null !== $line = yield $reader->readLine()) {
                $lines[] = $line;
            }

            self::assertSame($expectedLines, $lines);
        }));
    }
}
