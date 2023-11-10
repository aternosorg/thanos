<?php

namespace Aternos\Thanos\Reader;

/**
 * Interface ReaderInterface
 *
 * @package Aternos\Thanos\Reader
 */
interface ReaderInterface
{
    /**
     * Read $length bytes of data
     *
     * @param int $length
     * @return string
     */
    public function read(int $length): string;

    /**
     * Set pointer position to $offset
     *
     * @param int $offset
     */
    public function seek(int $offset): void;

    /**
     * Set pointer position to 0
     *
     */
    public function rewind(): void;

    /**
     * Get current pointer position
     *
     * @return int
     */
    public function tell(): int;

    /**
     * Check if pointer reached end of file
     *
     * @return bool
     */
    public function eof(): bool;

    /**
     * @return void
     */
    public function reset(): void;
}
