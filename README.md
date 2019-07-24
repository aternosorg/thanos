# Thanos

### About

Thanos is a PHP library to automatically detect and remove unused chunks from Minecraft worlds.
This can reduce the file size of a world by more than 50%.

Other than existing tools, this library does not use blocklists. Instead, the inhabited time value is used to determine whether a chunk is used or not. This prevents used chunks from sometimes being removed by accident and makes this library compatible with most mods and plugins.

Currently, only the Minecraft Anvil world format (Minecraft Java Edition) is supported.
### Installation

```bash
composer require aternos/thanos
```

## Usage

### CLI tool

This library includes a simple cli tool.

```bash
./thanos.php /path/to/world/directory [/path/to/output/directory]
```

### Worlds

A world object represents a Minecraft world with all its files.
It allows iteration over all chunks and provides a function to copy all non-region files to the output directory.
```php
$world = new Aternos\Thanos\World\AnvilWorld("/path/to/world/directory", "/path/to/output/directory");
$world->copyOtherFiles(); //copy non-region files

foreach ($world as $chunk){
  echo $chunk->getInhabitedTime() . "\n"; //output inhabited time for each chunk
}
```
After iterating over all chunks of a region file, it will be automatically saved to the output directory.

#### Methods

``getPath() : string`` Get world directory path

``getDestination() : string`` Get world output directory

``getOtherFiles() : string[]`` Get all files, that are not region directories

``copyOtherFiles() : void`` Copy all files, that are not region directories, to the output directory

``static isWorld(string $path) : bool`` Check if `$path` is world directory

### Chunks

A chunk object represents a Minecraft world chunk. Due to performance reasons, 
chunk data is not completely parsed but only used to find metadata that helps to determine whether a chunk is used.

```php
if($chunk->getInhabitedTime() > 0){
  $chunk->save();
}
```
If a chunk is not marked as saved, it will not be written to the output directory.

#### Methods

``getOffset() : int`` Get offset of chunk data within the region file

``getLength() : int`` Get length of raw chunk data

``getData() : string`` Get raw chunk data

``getInhabitedTime() : int`` Get InhabitedTime value from chunk data. Returns -1 if InhabitedTime could not be read.

``getLastUpdate() : int`` Get LastUpdate value from chunk data. Returns -1 if LastUpdate could not be read.

``setTimestamp(int $timestamp) : void`` Set last modification time

``getTimestamp() : int`` Get last modification time

``save() : void`` Mark chunk as saved

``isSaved() : bool`` Check whether this chunk is marked as saved

### Thanos
Thanos automatically finds unused chunks in a world and reduces them to atoms.

```php
$thanos = new Aternos\Thanos\Thanos();
$thanos->setMaxInhabitedTime(0);
$world = new Aternos\Thanos\World\AnvilWorld("/path/to/world/directory", "/path/to/output/directory");
$removedChunks = $thanos->snap($world);
echo "Removed $removedChunks chunks\n";
```

#### Methods

``setMinInhabitedTime(int $minInhabitedTime) : void`` Set min inhabited time for a chunk to not be removed

``getMinInhabitedTime() : int`` Get min inhabited time

``snap(WorldInterface $world) : int`` Remove unused chunks, returns the amount of chunks removed
