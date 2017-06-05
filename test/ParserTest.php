<?php

namespace Amp\ByteStream\Test;

use Amp\ByteStream\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase {
    public function testIntDelimiter() {
        $parser = new Parser((function () use (&$value) {
            $value = yield 6;
        })());

        $parser->push("foobarfoo\r\n");

        $this->assertSame("foobar", $value);
    }

    public function testStringDelimiter() {
        $parser = new Parser((function () use (&$value1, &$value2) {
            $value1 = yield "bar";
            $value2 = yield "\r\n";
        })());

        $parser->push("foobarbaz\r\n");

        $this->assertSame("foo", $value1);
        $this->assertSame("baz", $value2);
    }

    public function testUndelimited() {
        $parser = new Parser((function () use (&$value) {
            $value = yield;
        })());

        $parser->push("foobarbaz\r\n");

        $this->assertSame("foobarbaz\r\n", $value);
    }
}
