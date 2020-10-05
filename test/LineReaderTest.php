<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;

class LineReaderTest extends AsyncTestCase
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

    public function testClearBuffer()
    {
        $inputStream = new PipelineStream(Pipeline\fromIterable(["a\nb\nc"]));

        $reader = new LineReader($inputStream);
        self::assertSame("a", $reader->readLine());
        self::assertSame("b\nc", $reader->getBuffer());

        $reader->clearBuffer();

        self::assertSame("", $reader->getBuffer());
        self::assertNull($reader->readLine());
    }

    public function testCustomDelimiter()
    {
        $inputStream = new PipelineStream(Pipeline\fromIterable(["a|b|c", "|", "||d|e|f"]));

        $reader = new LineReader($inputStream, "|");
        $lines = [];

        while (null !== $line = $reader->readLine()) {
            $lines[] = $line;
        }

        self::assertSame(["a", "b", "c", "", "", "d", "e", "f"], $lines);
        self::assertSame("", $reader->getBuffer());
    }

    public function testLineFeedDelimiter()
    {
        $inputStream = new PipelineStream(Pipeline\fromIterable(["a\r\n", "b\r\n", "c\r\n"]));

        $reader = new LineReader($inputStream, "\n");
        $lines = [];

        while (null !== $line = $reader->readLine()) {
            $lines[] = $line;
        }

        self::assertSame(["a\r", "b\r", "c\r",], $lines);
        self::assertSame("", $reader->getBuffer());
    }

    private function check(array $chunks, array $expectedLines)
    {
        $inputStream = new PipelineStream(Pipeline\fromIterable($chunks));

        $reader = new LineReader($inputStream);
        $lines = [];

        while (null !== $line = $reader->readLine()) {
            $lines[] = $line;
        }

        self::assertSame($expectedLines, $lines);
        self::assertSame("", $reader->getBuffer());
    }
}
