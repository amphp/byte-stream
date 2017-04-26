<?php

namespace Amp\ByteStream;

/**
 */
class Buffer implements \ArrayAccess, \Countable, \IteratorAggregate {
    /** @var string */
    private $data;

    /**
     * Initialize buffer with the given string.
     *
     * @param string $data
     */
    public function __construct(string $data = '') {
        $this->data = $data;
    }

    /**
     * Current length of the buffer.
     *
     * @return int
     */
    public function getLength(): int {
        return \strlen($this->data);
    }

    /**
     * @return int
     */
    public function count(): int {
        return $this->getLength();
    }

    /**
     * Determines if the buffer is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool {
        return $this->data === '';
    }

    /**
     * Pushes the given string onto the end of the buffer.
     *
     * @param string $data
     */
    public function push(string $data) {
        $this->data .= $data;
    }

    /**
     * Puts the given string at the beginning of the buffer.
     *
     * @param string $data
     */
    public function unshift(string $data) {
        $this->data = $data . $this->data;
    }

    /**
     * @param int $length
     *
     * @return string
     */
    public function shift(int $length): string {
        if ($length <= 0) {
            return '';
        }

        if (\strlen($this->data) <= $length) {
            $buffer = $this->data;
            $this->data = '';
            return $buffer;
        }

        $buffer = (string) \substr($this->data, 0, $length);
        $this->data = (string) \substr($this->data, $length);

        return $buffer;
    }

    /**
     * Returns the given number of characters (at most) from the buffer without removing them from the buffer.
     *
     * @param int $length
     * @param int $offset
     *
     * @return string
     */
    public function peek(int $length, int $offset = 0): string {
        if ($length <= 0) {
            return '';
        }

        if ($offset < 0) {
            $offset = 0;
        }

        if ($offset === 0 && \strlen($this->data) <= $length) {
            return $this->data;
        }

        return (string) \substr($this->data, $offset, $length);
    }

    /**
     * @param int $length
     *
     * @return string
     */
    public function pop(int $length): string {
        if ($length <= 0) {
            return '';
        }

        $buffer = (string) \substr($this->data, -$length);

        $this->data = (string) \substr($this->data, 0, -$length);

        return $buffer;
    }

    /**
     * Removes and returns the given number of characters (at most) from the buffer.
     *
     * @param int $length
     * @param int $offset
     *
     * @return string
     */
    public function remove(int $length, int $offset): string {
        if ($length <= 0) {
            return '';
        }

        if ($offset < 0) {
            $offset = 0;
        }

        $buffer = (string) \substr($this->data, $offset, $length);

        if ($offset === 0) {
            $this->data = (string) \substr($this->data, $length);
        } else {
            $this->data = (string) (\substr($this->data, 0, $offset) . \substr($this->data, $offset + $length));
        }

        return $buffer;
    }

    /**
     * Removes and returns all data in the buffer.
     *
     * @return string
     */
    public function drain(): string {
        $buffer = $this->data;
        $this->data = '';
        return $buffer;
    }

    /**
     * Inserts the string at the given position in the buffer.
     *
     * @param string $string
     * @param int $position
     */
    public function insert(string $string, int  $position) {
        $this->data = \substr_replace($this->data, $string, $position, 0);
    }

    /**
     * Replaces all occurences of $search with $replace. See str_replace() function.
     *
     * @param mixed $search
     * @param mixed $replace
     *
     * @return int Number of replacements performed.
     */
    public function replace($search, $replace): int {
        $this->data = \str_replace($search, $replace, $this->data, $count);
        return $count;
    }

    /**
     * Returns the position of the given pattern in the buffer if it exists, or false if it does not.
     *
     * @param string $string String to search for.
     * @param bool $reverse Start search from end of buffer.
     *
     * @return int|bool
     *
     * @see strpos()
     */
    public function search(string $string, bool $reverse = false) {
        if ($reverse) {
            return \strrpos($this->data, $string);
        }

        return \strpos($this->data, $string);
    }

    /**
     * Determines if the buffer contains the given position.
     *
     * @param int $index
     *
     * @return bool
     */
    public function offsetExists($index) {
        return isset($this->data[$index]);
    }

    /**
     * Returns the character in the buffer at the given position.
     *
     * @param int $index
     *
     * @return string
     */
    public function offsetGet($index) {
        return $this->data[$index];
    }

    /**
     * Replaces the character in the buffer at the given position with the given string.
     *
     * @param int $index
     * @param string $data
     */
    public function offsetSet($index, $data) {
        $this->data = \substr_replace($this->data, $data, $index, 1);
    }

    /**
     * Removes the character at the given index from the buffer.
     *
     * @param int $index
     */
    public function offsetUnset($index) {
        if (isset($this->data[$index])) {
            $this->data = \substr_replace($this->data, null, $index, 1);
        }
    }

    /**
     * @return \Iterator
     */
    public function getIterator(): \Iterator {
        return new BufferIterator($this);
    }

    /**
     * @return string
     */
    public function __toString(): string {
        return $this->data;
    }
}
