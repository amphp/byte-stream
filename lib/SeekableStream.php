<?php

namespace Amp\ByteStream;

interface SeekableStream
{
    public const SEEK_SET = \SEEK_SET;
    public const SEEK_CUR = \SEEK_CUR;
    public const SEEK_END = \SEEK_END;

    /**
     * Set the internal pointer position.
     *
     * $whence values:
     *
     * SEEK_SET - Set position equal to offset bytes.
     * SEEK_CUR - Set position to current location plus offset.
     * SEEK_END - Set position to end-of-file plus offset.
     *
     * @param int $position
     * @param int $whence
     *
     * @return int New offset position.
     */
    public function seek(int $position, int $whence = self::SEEK_SET): int;

    /**
     * Return the current internal offset position of the file handle.
     *
     * @return int
     */
    public function tell(): int;

    /**
     * Test for being at the end of the stream (a.k.a. "end-of-file").
     *
     * @return bool
     */
    public function atEnd(): bool;
}
