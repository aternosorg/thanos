<?php

namespace Aternos\Thanos\World;

use Iterator;
use Aternos\Thanos\Chunk\ChunkInterface;

/**
 * Interface WorldInterface
 * Objects representing a Minecraft world
 * Allows iteration over all chunks
 *
 * @package Aternos\Thanos\World
 */
interface WorldInterface extends Iterator
{
    /**
     * Get world directory path
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
     * Get all files, that are not region directories
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
     *
     * @return ChunkInterface|null
     */
    public function current(): ?ChunkInterface;

    /**
     * Check if a directory is a world directory
     *
     * @param string $path
     * @return bool
     */
    public static function isWorld(string $path): bool;
}
