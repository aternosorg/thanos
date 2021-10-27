<?php

namespace Aternos\Thanos\Region;

use Aternos\Thanos\Chunk\ChunkInterface;
use Countable;

/**
 * Interface RegionInterface
 * Objects representing a Minecraft world region
 *
 * @package Aternos\Thanos\Region
 */
interface RegionInterface extends Countable
{
    /**
     * Get region file path
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
     * Get all chunks of this region
     *
     * @return ChunkInterface[]
     */
    public function getChunks(): array;

    /**
     * Check if region contains any chunks
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Get chunk at position $x $z
     *
     * @param int $x
     * @param int $z
     * @return ChunkInterface|null
     */
    public function getChunkAt(int $x, int $z): ?ChunkInterface;

    /**
     * Save this region file
     * @param bool $verify
     */
    public function save(bool $verify = true): void;
}
