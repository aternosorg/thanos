# Thanos

## About

Thanos is a PHP library to automatically detect and remove unused chunks from Minecraft worlds.
This can reduce the file size of a world by more than 50%.

Other than existing tools, this library does not use blocklists.
Instead, the inhabited time value is used to determine whether a chunk is used or not.
This prevents used chunks from sometimes being removed by accident and makes this library compatible with most mods and plugins.

Only Minecraft: Java Edition worlds are supported.
## Installation

```bash
composer require aternos/thanos
```

To work with LZ4 compressed chunks (Minecraft 1.20.5+), you should also install the [PHP LZ4 extension](https://github.com/kjdev/php-ext-lz4).

## Known issues

World features like trees that cross chunk borders can sometimes look cut off,
if the chunk containing half of the feature is removed while the other half is not.
Details can be found in this issue: https://github.com/aternosorg/thanos/issues/20

## CLI usage

This library includes a simple cli tool.

```bash
./thanos.php /path/to/world/directory [/path/to/output/directory]
```
Thanos uses [Taskmaster](https://github.com/aternosorg/taskmaster) for asynchronous tasks, which can be configured [using environment variables](https://github.com/aternosorg/taskmaster#defining-workers-using-environment-variables).

### Windows support

While Thanos will generally work on Windows, it will be much slower since asynchronous tasks are not supported.
It is therefore recommended to use the [Windows Subsystem for Linux](https://learn.microsoft.com/en-us/windows/wsl/install) to run Thanos on Windows.

## Library usage

To use Thanos as a library, create a Thanos object that is configured with the desired Patterns to match
chunks which should be kept.

```php
$thanos = new \Aternos\Thanos\Thanos([
    new \Aternos\Thanos\Pattern\RangePattern(0, 0, 16, 16), //keep chunks in range x:0 z:0 to x:16 z:16
]);
```

You can then pass a World object and a destination directory to the `snap` method to remove chunks that match
none of the patterns.

```php

$world = \Aternos\Thanos\World\World::open("/path/to/world/");
$destination = new \Aternos\IO\System\Directory\Directory("/path/to/output/");
$removedChunks = $thanos->snap($world, $destination);
echo "Removed " . $removedChunks . " chunks\n";
```

### Chunk patterns

To decide which chunks should be kept, Thanos uses chunk patterns, which implement the `ChunkPatternInterface`.
Built-in patterns include a `ListPattern`, which keeps chunks based on a predefined list of chunk coordinates,
and a `RangePattern`, which keeps all chunks in a defined rectangular area.

#### Pattern factories

Sometimes a pattern cannot be defined statically, but needs to be created based on the world data.
It is therefore possible to also pass instances of `WorldPatternFactoryInterface` for world-based pattern creation,
and `DimensionPatternFactoryInterface` for dimension-based pattern creation to the Thanos constructor.

An example for a dimension-based pattern factory is the `ForceLoadedChunksPatternFactory`. This factory reads
the list of force-loaded chunks for a dimension and creates a `ListPattern` to keep those chunks.

```php
$thanos = new \Aternos\Thanos\Thanos([
    new \Aternos\Thanos\Pattern\Factory\ForceLoadedChunkPatternFactory()
]);
```

### MCA reader and writer

Thanos includes an implementation that can be used to interact with Minecraft MCA files directly.

### McaReader

```php
$reader = \Aternos\Thanos\Mca\McaReader::open("/path/to/world/region/r.0.0.mca");
$chunk = $reader->getChunk(123); //Get at index 123 in region file
$chunk = $reader->getChunkAt(12, 23); //Get chunk at chunk coordinates x:12 z:23

// Iterate over all chunks in the region file
foreach ($reader->getChunks() as $chunk) {
    //Do something with chunk
}

$reader->close();
```

### McaWriter

```php
$writer = \Aternos\Thanos\Mca\McaWriter::open("/path/to/world/region/r.0.0.mca");
$writer->writeEntry($chunk); // Write a chunk to the MCA file

$writer->close(); // Finalize and close the MCA file

```
