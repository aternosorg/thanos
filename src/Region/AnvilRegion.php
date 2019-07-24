<?php


namespace Aternos\Thanos\Region;


use Aternos\Thanos\Chunk\AnvilChunk;
use Aternos\Thanos\Chunk\ChunkInterface;
use Exception;

/**
 * Class AnvilRegion
 * Object representing a Minecraft Anvil world region
 *
 * @package Aternos\Thanos\Region
 */
class AnvilRegion implements RegionInterface
{

    /**
     * @var resource
     */
    public $file;

    /**
     * @var string
     */
    public $path;

    /**
     * @var string
     */
    public $dest;

    /**
     * @var AnvilChunk[]
     */
    protected $chunks = [];

    /**
     * @var AnvilChunk[]
     */
    protected $existingChunks = [];

    /**
     * AnvilRegion constructor.
     * @param string $file
     * @param string $dest
     */
    public function __construct(string $file, string $dest)
    {
        $this->path = $file;
        $this->file = fopen($file, 'r');
        $this->dest = $dest;
        $this->readChunkTable();
    }

    public function __destruct()
    {
        fclose($this->file);
    }

    /**
     * Read offset, size and timestamps of all chunks
     *
     */
    protected function readChunkTable(): void
    {
        fseek($this->file, 0);
        $values = unpack('N1024', fread($this->file, 4 * 1024));
        foreach ($values as $val) {
            $offset = ($val >> 8) * 4096;
            $size = ($val & 0xFF) * 4096;
            if ($offset === 0 && $size === 0) {
                $this->chunks[] = null;
            } else {
                $chunk = new AnvilChunk($this->file, ($val >> 8) * 4096);
                $this->chunks[] = $chunk;
                $this->existingChunks[] = $chunk;
            }
        }

        fseek($this->file, 4096);
        $values = unpack('N1024', fread($this->file, 4 * 1024));
        $i = 0;
        foreach ($values as $val) {
            if ($this->chunks[$i] !== null) {
                $this->chunks[$i]->setTimestamp($val);
            }
            $i++;
        }
    }

    /**
     * Get chunk at position $x $z
     *
     * @param int $x
     * @param int $z
     * @return AnvilChunk
     */
    public function getChunkAt(int $x, int $z): ?ChunkInterface
    {
        return $this->chunks[($x % 32) + ($z % 32) * 32];
    }

    /**
     * Write region to $file
     *
     * @throws Exception
     */
    public function save(): void
    {
        if (!$this->hasExistingChunks()) {
            return;
        }
        $outputFile = fopen($this->dest, 'w');
        $chunkTable = [];
        $timestampTable = [];
        fwrite($outputFile, str_pad('', 8 * 1024, pack('C', 0)));

        foreach ($this->chunks as $i => $chunk) {
            if ($chunk === null || !$chunk->isSaved()) {
                $chunkTable[] = pack('N', 0);
                $timestampTable[] = pack('N', 0);
                continue;
            }

            fseek($this->file, $chunk->getOffset() + 5);
            $offset = ftell($outputFile);
            $size = $chunk->getLength();
            $padding = 4096 - ($size % 4096);
            $padding = ($padding === 4096 ? 0 : $padding);
            $chunkTable[] = pack('N', ((int)($offset / 4096) << 8) | ((int)(($size + $padding) / 4096) & 0xFF));
            $timestampTable[] = pack('N', $chunk->getTimestamp());
            fwrite($outputFile, pack('NC', $size - 4, $chunk->getCompression()));
            stream_copy_to_stream($this->file, $outputFile, $size - 5);
            fwrite($outputFile, str_pad('', $padding, pack('C', 0)));
            if (ftell($outputFile) % 4096 !== 0) {
                fclose($outputFile);
                throw new Exception('Failed to save region file to ' . $this->dest);
            }
        }
        fseek($outputFile, 0);
        fwrite($outputFile, implode('', $chunkTable));
        fwrite($outputFile, implode('', $timestampTable));
        fclose($outputFile);
    }

    protected function hasExistingChunks(): int
    {
        foreach ($this->chunks as $chunk) {
            if ($chunk !== null && $chunk->isSaved()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Count elements of an object
     * @link https://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     *
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return count($this->existingChunks);
    }

    /**
     * Get all chunks of this region
     *
     * @return AnvilChunk[]
     */
    public function getChunks(): array
    {
        return $this->existingChunks;
    }

    /**
     * Check if region contains no chunks
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return (count(array_keys($this->chunks, null)) === 0);
    }

    /**
     * Get region file path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
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
