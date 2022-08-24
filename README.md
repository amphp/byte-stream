# amphp/byte-stream

AMPHP is a collection of event-driven libraries for PHP designed with fibers and concurrency in mind.
`amphp/byte-stream` specifically provides a stream abstraction to ease working with various byte streams.

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/byte-stream
```

## Requirements

This package requires PHP 8.1 or later.

## Usage

Streams are an abstraction over ordered sequences of bytes. This package provides the fundamental interfaces `ReadableStream` and `WritableStream`.

> **Note**
> Previous versions used the terms `InputStream` and `OutputStream`, but these terms can be confusing depending on the use case.

### ReadableStream

`ReadableStream` offers a primary method: `read()`. It returns a `string` or `null`. `null` indicates that the stream has ended.

The following example shows a `ReadableStream` consumption that buffers the complete stream contents.

```php
$stream = ...;
$buffer = "";

while (($chunk = $stream->read()) !== null) {
    $buffer .= $chunk;
}

// do something with $buffer
```

> **Note**
> `Amp\ByteStream\buffer($stream)` can be used instead, but we'd like to demonstrate manual consumption here.

This package offers some basic implementations, other libraries might provide even more implementations, such as [`amphp/socket`](https://github.com/amphp/socket).

* [`Payload`](#Payload)
* [`ReadableBuffer`](#ReadableBuffer)
* [`ReadableIterableStream`](#ReadableIterableStream)
* [`ReadableResourceStream`](#ReadableResourceStream)
* [`ReadableStreamChain`](#ReadableStreamChain)
* [`Base64DecodingReadableStream`](#Base64DecodingReadableStream)
* [`Base64EncodingReadableStream`](#Base64EncodingReadableStream)
* [`CompressingReadableStream`](#CompressingReadableStream)

### WritableStream

`WritableStream` offers two primary methods: `write()` and `end()`.

#### write

`write()` writes the given string to the stream. Waiting for completion allows writing only as fast as the underlying stream can write and potentially send over a network. TCP streams will return immediately as long as the write buffer isn't full.

The writing order is always ensured, even if the writer doesn't wait for completion before issuing another write.

#### end

`end()` marks the stream as ended. TCP streams might close the underlying stream for writing, but MUST NOT close it. Instead, all resources should be freed and actual resource handles be closed by PHP's garbage collection process.

The following example uses the previous example to read from a stream and writes all data to a `WritableStream`:

```php
$readableStream = ...;
$writableStream = ...;
$buffer = "";

while (($chunk = $readableStream->read()) !== null) {
    $writableStream->write($chunk);
}

$writableStream->end();
```

> **Note**
> `Amp\ByteStream\pipe($readableStream, $writableStream)` can be used instead, but we'd like to demonstrate manual consumption / writing here.

This package offers some basic implementations, other libraries might provide even more implementations, such as [`amphp/socket`](https://github.com/amphp/socket).

* [`WritableBuffer`](#WritableBuffer)
* [`WritableIterableStream`](#WritableIterableStream)
* [`WritableResourceStream`](#WritableResourceStream)
* [`Base64DecodingWritableStream`](#Base64DecodingWritableStream)
* [`Base64EncodingWritableStream`](#Base64EncodingWritableStream)
* [`CompressingWritableStream`](#CompressingWritableStream)

## Versioning

`amphp/byte-stream` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
