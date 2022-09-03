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
* [`DecompressingReadableStream`](#DecompressingReadableStream)

## ReadableBuffer

An `ReadableBuffer` allows creating an `InputStream` from a single known string chunk.
This is helpful if the complete stream contents are already known.

```php
$stream = new ReadableBuffer("foobar");
```

It also allows creating a stream without any chunks by passing `null` as chunk.

```php
$stream = new ReadableBuffer;

// The stream ends immediately
assert(null === $stream->read());
```

## DecompressingReadableStream

This package implements compression based on Zlib. `CompressingWritableStream` can be used for compression, while `DecompressingReadableStream` can be used for decompression. Both can simply wrap an existing stream to apply them. Both accept an `$encoding` and `$options` parameter in their constructor.

```php
$readableStream = new ReadableResourceStream(STDIN);
$decompressingReadableStream = new DecompressingReadableStream($readableStream, \ZLIB_ENCODING_GZIP);

while (null !== $chunk = $decompressingReadableStream) {
    print $chunk;
}
```

See also: [`./examples/gzip-decompress.php`](https://github.com/amphp/byte-stream/blob/v2/examples/gzip-decompress.php)

### WritableStream

`WritableStream` offers two primary methods: `write()` and `end()`.

#### WritableStream::write

`write()` writes the given string to the stream. Waiting for completion allows writing only as fast as the underlying stream can write and potentially send over a network. TCP streams will return immediately as long as the write buffer isn't full.

The writing order is always ensured, even if the writer doesn't wait for completion before issuing another write.

#### WritableStream::end

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

### CompressingWritableStream

This package implements compression based on Zlib. `CompressingWritableStream` can be used for compression, while `DecompressingReadableStream` can be used for decompression. Both can simply wrap an existing stream to apply them. Both accept an `$encoding` and `$options` parameter in their constructor.

```php
$writableStream = new WritableResourceStream(STDOUT);
$compressedWritableStream = new CompressingWritableStream($writableStream, \ZLIB_ENCODING_GZIP);

for ($i = 0; $i < 100; $i++) {
    $compressedWritableStream->write(bin2hex(random_bytes(32));
}

$compressedWritableStream->end();
```

See also: [`./examples/gzip-compress.php`](https://github.com/amphp/byte-stream/blob/v2/examples/gzip-compress.php)

## Versioning

`amphp/byte-stream` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
