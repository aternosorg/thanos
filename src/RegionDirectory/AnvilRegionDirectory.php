<?php

namespace Aternos\Thanos\RegionDirectory;

use Aternos\Nbt\IO\Reader\GZipCompressedStringReader;
use Aternos\Nbt\NbtFormat;
use Aternos\Nbt\Tag\CompoundTag;
use Aternos\Nbt\Tag\Tag;
use Aternos\Thanos\Chunk\AnvilChunk;
use Exception;
use Aternos\Thanos\Helper;
use Aternos\Thanos\Region\AnvilRegion;

/**
 * Class AnvilRegionDirectory
 * Object representing a Minecraft Anvil region directory
 *
 * @package Aternos\Thanos\RegionDirectory
 */
class AnvilRegionDirectory implements RegionDirectoryInterface
{
    private const CURRENT_DIRECTORY = '.';

    private const PARENT_DIRECTORY = '..';

    private const FILE_EXTENSION_MCA = '.mca';

    /**
     * @var string
     */
    protected string $path;

    /**
     * @var string
     */
    protected string $dest;

    /**
     * @var string[]
     */
    protected array $files;

    /**
     * @var string[]
     */
    protected array $regionFiles = [];

    /**
     * @var string[]
     */
    protected array $otherFiles = [];

    /**
     * @var int
     */
    protected int $iterationIndex = 0;

    /**
     * @var int
     */
    protected int $regionPointer = 0;

    /**
     * @var int
     */
    protected int $chunkPointer = 0;

    /**
     * @var AnvilRegion|null
     */
    protected ?AnvilRegion $currentRegion = null;

    /**
     * @param string $path
     * @param string $dest
     * @throws Exception
     */
    public function __construct(string $path, string $dest)
    {
        $this->path = $path;
        $this->dest = $dest;

        $files = scandir($path);
        if($files === false) {
            throw new Exception("Failed to read directory " . $path);
        }
        $this->files = $files;

        foreach ($this->files as $file) {
            if (
                $file === static::CURRENT_DIRECTORY
                || $file === static::PARENT_DIRECTORY
            ) {
                continue;
            }

            if(is_link($path . DIRECTORY_SEPARATOR . $file)){
                continue;
            }

            if (
                substr($file, -4) === static::FILE_EXTENSION_MCA
                && is_file($path . DIRECTORY_SEPARATOR . $file)
            ) {
                if(filesize($path . DIRECTORY_SEPARATOR . $file) >= 4096){
                    $this->regionFiles[] = $file;
                }
                continue;
            }

            $this->otherFiles[] = $file;
        }
        if (count($this->regionFiles) > 0) {
            $this->currentRegion = new AnvilRegion(
                $this->path . DIRECTORY_SEPARATOR . $this->regionFiles[0],
                $this->dest . DIRECTORY_SEPARATOR . $this->regionFiles[0]
            );
        }
    }

    /**
     * Get directory path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get all region files in this Directory
     *
     * @return string[]
     */
    public function getRegionFiles(): array
    {
        return $this->regionFiles;
    }

    /**
     * Get all regions in this directory (this will read all region file headers and all chunk headers)
     *
     * @return AnvilRegion[]
     * @throws Exception
     */
    public function getRegions(): array
    {
        $regions = [];
        foreach ($this->regionFiles as $regionFile) {
            $regions[] = new AnvilRegion(
                $this->path . DIRECTORY_SEPARATOR . $regionFile,
                $this->dest . DIRECTORY_SEPARATOR . $regionFile
            );
        }
        return $regions;
    }

    /**
     * Try to save current region, copy region file if an error occurs
     *
     */
    protected function saveCurrentRegion(): void
    {
        if ($this->currentRegion !== null) {
            try {
                $this->currentRegion->save();
            } catch (Exception $e) {
                copy(
                    $this->currentRegion->getPath(),
                    $this->currentRegion->getDestination()
                );
            }
        }
    }

    /**
     * Check if a directory is a region directory
     *
     * @param string $path
     * @return bool
     */
    static function isRegionDirectory(string $path): bool
    {
        $isRegion = false;
        if (is_dir($path)) {
            foreach (scandir($path) as $file) {
                if (
                    $file !== static::CURRENT_DIRECTORY
                    && $file !== static::PARENT_DIRECTORY
                    && is_file($path . DIRECTORY_SEPARATOR . $file)
                ) {
                    if (substr($file, -4) === static::FILE_EXTENSION_MCA) {
                        $isRegion = true;
                        break;
                    }
                }
            }
        }

        return $isRegion;
    }

    /**
     * Return the current element
     * @link https://php.net/manual/en/iterator.current.php
     * @return AnvilChunk|null
     * @since 5.0.0
     */
    public function current(): ?AnvilChunk
    {
        return $this->currentRegion !== null
            ? $this->currentRegion->getChunks()[$this->chunkPointer]
            : null
        ;
    }

    /**
     * Move forward to next element
     * @link https://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     * @throws Exception
     */
    public function next(): void
    {
        $this->iterationIndex++;
        if (isset($this->currentRegion->getChunks()[$this->chunkPointer + 1])) {
            $this->chunkPointer++;
        } else {
            $this->chunkPointer = 0;
            do {
                $this->saveCurrentRegion();
                $this->regionPointer++;
                if ($this->regionPointer >= count($this->regionFiles)) {
                    break;
                }

                $this->currentRegion = new AnvilRegion(
                    $this->path . DIRECTORY_SEPARATOR . $this->regionFiles[$this->regionPointer],
                    $this->dest . DIRECTORY_SEPARATOR . $this->regionFiles[$this->regionPointer]
                );
            } while (count($this->currentRegion->getChunks()) === 0);
        }
    }

    /**
     * Return the key of the current element
     * @link https://php.net/manual/en/iterator.key.php
     * @return int scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key(): int
    {
        return $this->iterationIndex;
    }

    /**
     * Checks if current position is valid
     * @link https://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be cast to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid(): bool
    {
        return $this->regionPointer < count($this->regionFiles);
    }

    /**
     * Rewind the Iterator to the first element
     * @link https://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind(): void
    {
        $this->saveCurrentRegion();
        $this->regionPointer = 0;
        $this->chunkPointer = 0;
        $this->iterationIndex = 0;
    }

    /**
     * Get all files, that are not region files
     *
     * @return string[]
     */
    public function getOtherFiles(): array
    {
        return $this->otherFiles;
    }

    /**
     * Copy all other files to $dest
     *
     */
    public function copyOtherFiles(): void
    {
        mkdir($this->dest, 0777, true);
        foreach ($this->otherFiles as $file) {
            if(is_link($this->path . DIRECTORY_SEPARATOR . $file)){
                continue;
            }
            if (is_dir($this->path . DIRECTORY_SEPARATOR . $file)) {
                Helper::copyDirectory(
                    $this->path . DIRECTORY_SEPARATOR . $file,
                    $this->dest . DIRECTORY_SEPARATOR . $file
                );
            } else {
                copy(
                    $this->path . DIRECTORY_SEPARATOR . $file,
                    $this->dest . DIRECTORY_SEPARATOR . $file
                );
            }
        }
    }

    /**
     * Save all remaining changes
     *
     */
    public function saveAll(): void
    {
        $this->saveCurrentRegion();
    }

    /**
     * Get destination
     *
     * @return string
     */
    public function getDestination(): string
    {
        return $this->dest;
    }

    /**
     * @param string $filename
     * @return Tag|null
     * @throws Exception
     */
    public function readDataFile(string $filename): ?Tag
    {
        $path = dirname($this->getPath()) . DIRECTORY_SEPARATOR . "data" . DIRECTORY_SEPARATOR . $filename;
        if(!file_exists($path)) {
            return null;
        }
        return Tag::load(new GZipCompressedStringReader(file_get_contents($path), NbtFormat::JAVA_EDITION));
    }

    /**
     * @inheritDoc
     */
    public function getForceLoadedChunks(): array
    {
        $chunksDat = $this->readDataFile("chunks.dat");
        if(!$chunksDat instanceof CompoundTag) {
            return [];
        }

        $data = $chunksDat->getCompound("data");
        if($data === null) {
            return [];
        }

        $list = $data->getLongArray("Forced");
        if($list === null) {
            return [];
        }

        $data = $list->getRawValue();
        $coordinates = [];
        $currentCoordinate = [];
        for($i = 0; $i < count($list)*2; $i++) {
            $currentCoordinate[] = unpack("N", $data, $i*4)[1] << 32 >> 32;
            if($i % 2 === 1) {
                $coordinates[] = $currentCoordinate;
                $currentCoordinate = [];
            }
        }
        return $coordinates;
    }
}
