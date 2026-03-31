# CCSDS PHP SDK

A PHP library for parsing [CCSDS](https://ccsds.org) (Consultative Committee for Space Data Systems) files. Currently supports **Orbit Ephemeris Messages (OEM)**.

## Requirements

- PHP 8.4+
- [league/flysystem](https://flysystem.thephpleague.com/) ^3.33

## Installation

```bash
composer require harmonicdigital/ccsds
```

## Usage

### Parsing an OEM file

`CcsdsClient` accepts any [Flysystem](https://flysystem.thephpleague.com/) `FilesystemReader`, giving you flexibility over where your files are stored - local disk, S3, SFTP, in-memory, etc.

```php
use HarmonicDigital\Ccsds\CcsdsClient;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

$filesystem = new Filesystem(new LocalFilesystemAdapter('/path/to/data'));
$client = new CcsdsClient($filesystem);

$oemFile = $client->parseOemFile('ephemeris/H20090909_0001.LOE');
```

### Working with the parsed file

```php
// File-level header
echo $oemFile->header->version;      // e.g. "1.0"
echo $oemFile->header->originator;   // e.g. "ESOC"
echo $oemFile->header->creationDate->format('Y-m-d\TH:i:s');

// File-level comments
foreach ($oemFile->comments as $comment) {
    echo $comment . PHP_EOL;
}

// Segments - each covers a contiguous time range
foreach ($oemFile as $segment) {
    $meta = $segment->metadata;

    echo $meta->objectName;          // e.g. "HERSCHEL"
    echo $meta->objectId;            // e.g. "2009-026A"
    echo $meta->refFrame;            // e.g. "EME2000"
    echo $meta->timeSystem;          // e.g. "TDB"
    echo $meta->startTime->format('Y-m-d\TH:i:s.u');
    echo $meta->stopTime->format('Y-m-d\TH:i:s.u');

    // State vectors - position in km, velocity in km/s
    foreach ($segment->stateVectors as $sv) {
        echo $sv->epoch->format('Y-m-d\TH:i:s.u');
        echo "  x={$sv->x}  y={$sv->y}  z={$sv->z}";
        echo "  xDot={$sv->xDot}  yDot={$sv->yDot}  zDot={$sv->zDot}";
        echo PHP_EOL;
    }
}
```

### Lazy parsing

The parser reads the file header eagerly, then yields segments lazily via a `CachedIterator`. This means:

- Accessing `$oemFile->header` and `$oemFile->comments` never triggers segment loading.
- Segments are parsed on demand as you iterate.
- Subsequent iterations replay from the cache - the underlying stream is only read once.

```php
// Safe to iterate multiple times at no extra I/O cost
$segments = iterator_to_array($oemFile);
$same     = iterator_to_array($oemFile); // served from cache
```

### Error handling

```php
use HarmonicDigital\Ccsds\Exception\ParseException;
use League\Flysystem\UnableToReadFile;

try {
    $oemFile = $client->parseOemFile('missing_or_corrupt.oem');
    foreach ($oemFile as $segment) { /* ... */ }
} catch (UnableToReadFile $e) {
    // Flysystem could not open the file
} catch (ParseException $e) {
    // The file content does not conform to the OEM format
}
```

> **Note:** `ParseException` may be thrown during header parsing (immediately) or during segment iteration (lazily). Wrap the full iteration in the same `try/catch` block.

### Custom parser

You can substitute your own OEM parser by implementing `OemParserInterface`:

```php
use HarmonicDigital\Ccsds\CcsdsClient;
use HarmonicDigital\Ccsds\Parser\OemParserInterface;

$client = new CcsdsClient($filesystem, new MyCustomOemParser());
```

## Data model

| Class | Description |
|---|---|
| `OemFile` | Top-level result. Contains a `Header`, file-level comments, and iterable `OemSegment`s. |
| `Header` | `version`, `creationDate` (`DateTimeImmutable`), `originator`. |
| `OemSegment` | A `Metadata` block paired with its `StateVector[]`. Also iterable directly over state vectors. |
| `Metadata` | Object name, ID, reference frame, time system, start/stop times, and optional interpolation settings. |
| `StateVector` | Epoch (`DateTimeImmutable`) + position (x/y/z km) + velocity (xDot/yDot/zDot km/s) stored as `numeric-string` to preserve decimal precision. |

## Running tests

```bash
composer install
./vendor/bin/phpunit
```

## License

MIT-NON-AI - see [LICENSE](LICENSE).