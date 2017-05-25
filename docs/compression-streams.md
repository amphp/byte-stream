# Compression Streams

This package implements compression and decompression streams based on Zlib. `ZlibOutputStream` can be used for compression, while `ZlibInputStream` can be used for decompression. Both can simply wrap an existing stream to apply them. Both accept an `$encoding` and `$options` parameter in their constructor.

## Examples

```php
$inputStream = new ResourceInputStream(STDIN);
$gzInputStream = new ZlibInputStream($inputStream, \ZLIB_ENCODING_GZIP);

while (null !== $chunk = yield $gzInputStream) {
    print $chunk;
}
```

```php
$outputStream = new ResourceOutputStream(STDOUT);
$gzOutputStream = new ZlibOutputStream($outputStream, \ZLIB_ENCODING_GZIP);

for ($i = 0; $i < 100; $i++) {
    yield $gzOutputStream->write(bin2hex(random_bytes(32));
}

yield $gzOutputStream->end();
```

## See also

 * [`./examples/gzip-compress.php`](../examples/gzip-compress.php)
 * [`./examples/gzip-decompress.php`](../examples/gzip-decompress.php)
