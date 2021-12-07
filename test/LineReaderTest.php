<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;

class LineReaderTest extends AsyncTestCase
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

    public function testEmptyCr(): void
    {
        $this->check(["\r"], [""]);
    }

    public function testMultiLineSlow(): void
    {
        $this->check(["a", "bc", "\r", "\n\r\nef\r", "\n"], ["abc", "", "ef"]);
    }

    public function testClearBuffer(): void
    {
        $inputStream = new IterableStream(Pipeline\fromIterable(["a\nb\nc"]));

        $reader = new LineReader($inputStream);
        self::assertSame("a", $reader->readLine());
        self::assertSame("b\nc", $reader->getBuffer());

        $reader->clearBuffer();

        self::assertSame("", $reader->getBuffer());
        self::assertNull($reader->readLine());
    }

    public function testCustomDelimiter(): void
    {
        $inputStream = new IterableStream(Pipeline\fromIterable(["a|b|c", "|", "||d|e|f"]));

        $reader = new LineReader($inputStream, "|");
        $lines = [];

        while (null !== $line = $reader->readLine()) {
            $lines[] = $line;
        }

        self::assertSame(["a", "b", "c", "", "", "d", "e", "f"], $lines);
        self::assertSame("", $reader->getBuffer());
    }

    public function testLineFeedDelimiter(): void
    {
        $inputStream = new IterableStream(Pipeline\fromIterable(["a\r\n", "b\r\n", "c\r\n"]));

        $reader = new LineReader($inputStream, "\n");
        $lines = [];

        while (null !== $line = $reader->readLine()) {
            $lines[] = $line;
        }

        self::assertSame(["a\r", "b\r", "c\r",], $lines);
        self::assertSame("", $reader->getBuffer());
    }

    private function check(array $chunks, array $expectedLines): void
    {
        $inputStream = new IterableStream(Pipeline\fromIterable($chunks));

        $reader = new LineReader($inputStream);
        $lines = [];

        while (null !== $line = $reader->readLine()) {
            $lines[] = $line;
        }

        self::assertSame($expectedLines, $lines);
        self::assertSame("", $reader->getBuffer());
    }
}
