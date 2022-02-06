<?php

namespace Amp\ByteStream\Internal;

use Amp\Parser\Parser;
use Amp\Serialization\NativeSerializer;
use Amp\Serialization\SerializationException;
use Amp\Serialization\Serializer;
use Amp\Sync\ChannelException;
use function Amp\Serialization\encodeUnprintableChars;

/** @internal */
final class ChannelParser extends Parser
{
    private const HEADER_LENGTH = 5;

    /**
     * @param \Closure(mixed):void $push
     * @param Serializer $serializer
     *
     * @return \Generator
     *
     * @throws ChannelException
     */
    private static function parser(\Closure $push, Serializer $serializer): \Generator
    {
        while (true) {
            /** @var string $header */
            $header = yield self::HEADER_LENGTH;
            $data = \unpack("Cprefix/Llength", $header);

            if ($data["prefix"] !== 0) {
                $data = $header . yield;
                throw new ChannelException("Invalid packet received: " . encodeUnprintableChars($data));
            }

            try {
                $data = $serializer->unserialize(yield $data["length"]);
            } catch (SerializationException $exception) {
                throw new ChannelException(
                    "Failed to unserialize the channel data: " . $exception->getMessage(),
                    0,
                    $exception,
                );
            }

            try {
                $push($data);
            } catch (\Throwable $exception) {
                throw new ChannelException(
                    "Invoking the parser callback failed: " . $exception->getMessage(),
                    0,
                    $exception,
                );
            }
        }
    }

    private Serializer $serializer;

    /**
     * @param \Closure(mixed):void $onMessage Closure invoked when data is parsed.
     * @param Serializer|null $serializer
     */
    public function __construct(\Closure $onMessage, ?Serializer $serializer = null)
    {
        $this->serializer = $serializer ?? new NativeSerializer;
        parent::__construct(self::parser($onMessage, $this->serializer));
    }

    /**
     * @param mixed $data Data to encode to send over a channel.
     *
     * @return string Encoded data that can be parsed by this class.
     *
     * @throws ChannelException
     */
    public function encode(mixed $data): string
    {
        try {
            $data = $this->serializer->serialize($data);
        } catch (SerializationException $exception) {
            throw new ChannelException(
                "Serializing the channel data failed: " . $exception->getMessage(),
                0,
                $exception,
            );
        }
        return \pack("CL", 0, \strlen($data)) . $data;
    }
}
