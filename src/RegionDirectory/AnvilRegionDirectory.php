<?php

namespace Aternos\Thanos\RegionDirectory;

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

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $dest;

    /**
     * @var string[]
     */
    protected $files;

    /**
     * @var string[]
     */
    protected $regionFiles;

    /**
     * @var string[]
     */
    protected $otherFiles;

    /**
     * @var int
     */
    protected $iterationIndex = 0;

    /**
     * @var int
     */
    protected $regionPointer = 0;

    /**
     * @var int
     */
    protected $chunkPointer = 0;

    /**
     * @var AnvilRegion
     */
    protected $currentRegion;

    public function __construct(string $path, string $dest)
    {
        $this->path = $path;
        $this->dest = $dest;
        $this->files = scandir($path);
        $this->regionFiles = [];
        $this->otherFiles = [];
        foreach ($this->files as $file){
            if($file === '.' || $file === '..'){
                continue;
            }
            if(substr($file,-4) === '.mca' && is_file("$path/$file")){
                $this->regionFiles[] = $file;
                continue;
            }
            $this->otherFiles[] = $file;
        }
        if(count($this->regionFiles) > 0){
            $this->currentRegion = new AnvilRegion("$this->path/" . $this->regionFiles[0],
                "$this->dest/" . $this->regionFiles[0]);
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
     */
    public function getRegions(): array
    {
        $regions = [];
        foreach ($this->regionFiles as $regionFile){
            $regions[] = new AnvilRegion("$this->path/$regionFile", "$this->dest/$regionFile");
        }
        return $regions;
    }

    /**
     * Try to save current region, copy region file if an error occurs
     *
     */
    protected function saveCurrentRegion(): void
    {
        if(!$this->currentRegion){
            return;
        }
        try{
            $this->currentRegion->save();
        }catch (Exception $e){
            copy($this->currentRegion->getPath(), $this->currentRegion->getDestination());
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
        if(!is_dir($path)){
            return false;
        }
        foreach (scandir($path) as $file){
            if($file === '.' || $file === '..'){
                continue;
            }
            if(!is_file("$path/$file")){
                continue;
            }
            if(substr($file, -4) === '.mca'){
                return true;
            }
        }
        return false;
    }

    /**
     * Return the current element
     * @link https://php.net/manual/en/iterator.current.php
     * @return AnvilChunk
     * @since 5.0.0
     */
    public function current()
    {
        if(!isset($this->currentRegion)){
            return null;
        }
        return $this->currentRegion->getChunks()[$this->chunkPointer];
    }

    /**
     * Move forward to next element
     * @link https://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     * @throws Exception
     */
    public function next()
    {
        $this->iterationIndex++;
        if(isset($this->currentRegion->getChunks()[$this->chunkPointer+1])){
            $this->chunkPointer++;
            return;
        }
        $this->chunkPointer = 0;
        $this->saveCurrentRegion();
        $this->regionPointer++;
        if($this->regionPointer >= count($this->regionFiles)){
            return;
        }
        $this->currentRegion = new AnvilRegion("$this->path/" . $this->regionFiles[$this->regionPointer],
            "$this->dest/" . $this->regionFiles[$this->regionPointer]);
    }

    /**
     * Return the key of the current element
     * @link https://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return $this->iterationIndex;
    }

    /**
     * Checks if current position is valid
     * @link https://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        return $this->regionPointer < count($this->regionFiles);
    }

    /**
     * Rewind the Iterator to the first element
     * @link https://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
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
    public function getFiles(): array
    {
        return $this->otherFiles;
    }

    /**
     * Copy all other files to $dest
     *
     */
    public function copyFiles(): void
    {
        @mkdir($this->dest);
        foreach ($this->otherFiles as $file){
            if(is_dir("$this->path/$file")){
                Helper::copy_directory("$this->path/$file", "$this->dest/$file");
            }else{
                copy("$this->path/$file", "$this->dest/$file");
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
}
