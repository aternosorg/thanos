<?php

namespace Aternos\Thanos\RegionDirectory;

use Iterator;
use Aternos\Thanos\Region\RegionInterface;

/**
 * Interface RegionDirectoryInterface
 * Objects representing a Minecraft region directory
 *
 * @package Aternos\Thanos\RegionDirectory
 */
interface RegionDirectoryInterface extends Iterator
{
    /**
     * Get directory path
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Get destination
     *
     * @return string
     */
    public function getDestination(): string;

    /**
     * Get all region files in this Directory
     *
     * @return string[]
     */
    public function getRegionFiles(): array;

    /**
     * Get all files, that are not region files
     *
     * @return string[]
     */
    public function getOtherFiles(): array;

    /**
     * Copy all other files to $dest
     *
     */
    public function copyOtherFiles(): void;

    /**
     * Get all regions in this directory (this will read all region file headers and all chunk headers)
     *
     * @return RegionInterface[]
     */
    public function getRegions(): array;

    /**
     * Save all remaining changes
     *
     */
    public function saveAll(): void;

    /**
     * @return int[][]
     */
    public function getForceLoadedChunks(): array;

    /**
     * Check if a directory is a region directory
     *
     * @param string $path
     * @return bool
     */
    static function isRegionDirectory(string $path): bool;
}
