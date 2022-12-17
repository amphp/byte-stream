<?php declare(strict_types=1);

namespace Amp\ByteStream\Internal;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Serialization\SerializationException;
use Amp\Sync\ChannelException;

class ChannelParserTest extends AsyncTestCase
{
    public function testCorruptedData(): void
    {
        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('Exception thrown when unserializing data');

        $data = "Invalid serialized data";
        $data = \pack("CL", 0, \strlen($data)) . $data;
        $parser = new ChannelParser($this->createCallback(0));
        $parser->push($data);
    }

    public function testInvalidHeaderData(): void
    {
        $this->expectException(ChannelException::class);
        $this->expectExceptionMessage('Invalid packet received: Invalid packet');

        $data = "Invalid packet";
        $parser = new ChannelParser($this->createCallback(0));
        $parser->push($data);
    }

    public function testInvalidHeaderBinaryData(): void
    {
        $this->expectException(ChannelException::class);
        $this->expectExceptionMessage('Invalid packet received: B \xf3\xf2\x0\x1');

        $data = "\x42\x20\xf3\xf2\x00\x01";
        $parser = new ChannelParser($this->createCallback(0));
        $parser->push($data);
    }
}
