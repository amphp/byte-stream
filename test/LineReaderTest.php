<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\Iterator;
use Amp\PHPUnit\AsyncTestCase;

class LineReaderTest extends AsyncTestCase
{
    public function testSingleLine()
    {
        yield from $this->check(["abc"], ["abc"]);
    }

    public function testMultiLineLf()
    {
        yield from $this->check(["abc\nef"], ["abc", "ef"]);
    }

    public function testMultiLineCrLf()
    {
        yield from $this->check(["abc\r\nef"], ["abc", "ef"]);
    }

    public function testMultiLineEmptyNewlineStart()
    {
        yield from $this->check(["\r\nabc\r\nef\r\n"], ["", "abc", "ef"]);
    }

    public function testMultiLineEmptyNewlineEnd()
    {
        yield from $this->check(["abc\r\nef\r\n"], ["abc", "ef"]);
    }

    public function testMultiLineEmptyNewlineMiddle()
    {
        yield from $this->check(["abc\r\n\r\nef\r\n"], ["abc", "", "ef"]);
    }

    public function testEmpty()
    {
        yield from $this->check([], []);
    }

    public function testEmptyCrLf()
    {
        yield from $this->check(["\r\n"], [""]);
    }

    public function testEmptyCr()
    {
        yield from $this->check(["\r"], [""]);
    }

    public function testMultiLineSlow()
    {
        yield from $this->check(["a", "bc", "\r", "\n\r\nef\r", "\n"], ["abc", "", "ef"]);
    }

    public function testClearBuffer()
    {
        $inputStream = new IteratorStream(Iterator\fromIterable(["a\nb\nc"]));

        $reader = new LineReader($inputStream);
        self::assertSame("a", yield $reader->readLine());
        self::assertSame("b\nc", $reader->getBuffer());

        $reader->clearBuffer();

        self::assertSame("", $reader->getBuffer());
        self::assertNull(yield $reader->readLine());
    }

    public function testCustomDelimiter()
    {
        $inputStream = new IteratorStream(Iterator\fromIterable(["a|b|c", "|", "||d|e|f"]));

        $reader = new LineReader($inputStream, "|");
        $lines = [];

        while (null !== $line = yield $reader->readLine()) {
            $lines[] = $line;
        }

        self::assertSame(["a", "b", "c", "", "", "d", "e", "f"], $lines);
        self::assertSame("", $reader->getBuffer());
    }

    public function testLineFeedDelimiter()
    {
        $inputStream = new IteratorStream(Iterator\fromIterable(["a\r\n", "b\r\n", "c\r\n"]));

        $reader = new LineReader($inputStream, "\n");
        $lines = [];

        while (null !== $line = yield $reader->readLine()) {
            $lines[] = $line;
        }

        self::assertSame(["a\r", "b\r", "c\r",], $lines);
        self::assertSame("", $reader->getBuffer());
    }

    private function check(array $chunks, array $expectedLines)
    {
        $inputStream = new IteratorStream(Iterator\fromIterable($chunks));

        $reader = new LineReader($inputStream);
        $lines = [];

        while (null !== $line = yield $reader->readLine()) {
            $lines[] = $line;
        }

        self::assertSame($expectedLines, $lines);
        self::assertSame("", $reader->getBuffer());
    }
}
