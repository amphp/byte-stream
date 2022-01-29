<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\ByteStream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline\Pipeline;

final class SplitTest extends AsyncTestCase
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

    public function testCustomDelimiter(): void
    {
        $stream = new IterableStream(Pipeline::fromIterable(["a|b|c", "|", "||d|e|f"]));

        $lines = [];
        foreach (split($stream, '|') as $line) {
            $lines[] = $line;
        }

        self::assertSame(["a", "b", "c", "", "", "d", "e", "f"], $lines);
    }

    public function testLineFeedDelimiter(): void
    {
        $stream = new IterableStream(Pipeline::fromIterable(["a\r\n", "b\r\n", "c\r\n"]));

        $lines = [];
        foreach (split($stream, "\n") as $line) {
            $lines[] = $line;
        }

        self::assertSame(["a\r", "b\r", "c\r",], $lines);
    }

    private function check(array $chunks, array $expectedLines): void
    {
        $stream = new IterableStream(Pipeline::fromIterable($chunks));

        $lines = [];
        foreach (splitLines($stream) as $line) {
            $lines[] = $line;
        }

        self::assertSame($expectedLines, $lines);
    }
}
