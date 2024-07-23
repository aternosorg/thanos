<?php

namespace Aternos\Thanos\Chunk;

/**
 * Interface ChunkInterface
 * Objects representing a Minecraft world chunk
 *
 * @package Aternos\Thanos\Chunk
 */
interface ChunkInterface
{
    /**
     * Get offset of chunk data within the region file
     *
     * @return int
     */
    public function getOffset(): int;

    /**
     * Get length of chunk data
     *
     * @return int
     */
    public function getLength(): int;

    /**
     * Get InhabitedTime
     *
     * @return int
     */
    public function getInhabitedTime(): int;

    /**
     * Get time of last chunk update
     *
     * @return int
     */
    public function getLastUpdate(): int;

    /**
     * Set last modified time
     *
     * @param int $timestamp
     */
    public function setTimestamp(int $timestamp): void;

    /**
     * Get last modified time
     *
     * @return int
     */
    public function getTimestamp(): int;

    /**
     * Get the raw chunk data
     *
     * @return string
     */
    public function getData(): string;

    /**
     * Save this chunk
     *
     */
    public function save(): void;

    /**
     * Check if this chunk is saved
     *
     * @return bool
     */
    public function isSaved(): bool;

    /**
     * @return void
     */
    public function close(): void;

    /**
     * @return int
     */
    public function getGlobalXPos(): int;

    /**
     * @return int
     */
    public function getGlobalYPos(): int;
}
