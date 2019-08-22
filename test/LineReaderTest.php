<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\Iterator;
use Amp\PHPUnit\TestCase;
use function Amp\call;
use function Amp\Promise\wait;

class LineReaderTest extends TestCase
{
    public function testSingleLine()
    {
        $this->check(["abc"], ["abc"]);
    }

    public function testMultiLineLf()
    {
        $this->check(["abc\nef"], ["abc", "ef"]);
    }

    public function testMultiLineCrLf()
    {
        $this->check(["abc\r\nef"], ["abc", "ef"]);
    }

    public function testMultiLineEmptyNewlineStart()
    {
        $this->check(["\r\nabc\r\nef\r\n"], ["", "abc", "ef"]);
    }

    public function testMultiLineEmptyNewlineEnd()
    {
        $this->check(["abc\r\nef\r\n"], ["abc", "ef"]);
    }

    public function testMultiLineEmptyNewlineMiddle()
    {
        $this->check(["abc\r\n\r\nef\r\n"], ["abc", "", "ef"]);
    }

    public function testEmpty()
    {
        $this->check([], []);
    }

    public function testEmptyCrLf()
    {
        $this->check(["\r\n"], [""]);
    }

    public function testEmptyCr()
    {
        $this->check(["\r"], [""]);
    }

    public function testMultiLineSlow()
    {
        $this->check(["a", "bc", "\r", "\n\r\nef\r", "\n"], ["abc", "", "ef"]);
    }

    private function check(array $chunks, array $expectedLines)
    {
        wait(call(static function () use ($chunks, $expectedLines) {
            $inputStream = new IteratorStream(Iterator\fromIterable($chunks));

            $reader = new LineReader($inputStream);
            $lines = [];

            while (null !== $line = yield $reader->readLine()) {
                $lines[] = $line;
            }

            self::assertSame($expectedLines, $lines);
            self::assertSame("", $reader->getBuffer());
        }));
    }
}
