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
    public string $path;

    /**
     * @var string
     */
    public string $dest;

    /**
     * @var AnvilChunk[]
     */
    protected array $chunks = [];

    /**
     * @var AnvilChunk[]
     */
    protected array $existingChunks = [];

    /**
     * @var int
     */
    protected int $xPos;

    /**
     * @var int
     */
    protected int $yPos;

    /**
     * AnvilRegion constructor.
     * @param string $file
     * @param string $dest
     * @throws Exception
     */
    public function __construct(string $file, string $dest)
    {
        $this->path = $file;
        $this->file = fopen($file, 'r');
        $this->dest = $dest;
        $this->findRegionPosition();
        $this->readChunkTable();
    }

    public function __destruct()
    {
        fclose($this->file);
    }

    /**
     * @throws Exception
     */
    protected function findRegionPosition(): void
    {
        preg_match("#r\.(-?\d+)\.(-?\d+)\.mca$#", $this->path, $matches);
        if(!isset($matches[1]) || !isset($matches[2])) {
            throw new Exception("Unable to get region position from file name");
        }
        $this->xPos = intval($matches[1]);
        $this->yPos = intval($matches[2]);
    }

    /**
     * Read offset, size and timestamps of all chunks
     *
     * @throws Exception
     */
    protected function readChunkTable(): void
    {
        fseek($this->file, 0);
        $rawData = fread($this->file, 4 * 1024);
        if($rawData === false || strlen($rawData) !== 4 * 1024) {
            throw new Exception("Failed to read chunk table in '" . $this->getPath() . "'.");
        }
        $values = unpack('N1024', $rawData);
        if($values === false) {
            throw new Exception("Failed to decode chunk table in '" . $this->getPath() . "'.");
        }
        foreach ($values as $i => $val) {
            $offset = ($val >> 8) * 4096;
            $size = ($val & 0xFF) * 4096;
            if ($offset === 0 && $size === 0) {
                $this->chunks[] = null;
            } else {
                $chunk = new AnvilChunk($this->file, ($val >> 8) * 4096, [$this->getXPos(), $this->getYPos()], $i);
                $this->chunks[] = $chunk;
                $this->existingChunks[] = $chunk;
            }
        }

        fseek($this->file, 4096);
        $rawData = fread($this->file, 4 * 1024);
        if($rawData === false || strlen($rawData) !== 4 * 1024) {
            throw new Exception("Failed to read timestamp table in '" . $this->getPath() . "'.");
        }
        $values = unpack('N1024', $rawData);
        if($values === false) {
            throw new Exception("Failed to decode timestamp table in '" . $this->getPath() . "'.");
        }
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
     * @return AnvilChunk|null
     */
    public function getChunkAt(int $x, int $z): ?ChunkInterface
    {
        $position = ($x % 32) + ($z % 32) * 32;

        return $this->chunks[$position];
    }

    /**
     * Write region to $file
     * If there are chunks
     *
     * @param bool $verify
     * @throws Exception
     */
    public function save(bool $verify = true): void
    {
        if ($this->hasExistingChunks()) {
            $this->writeToFile(
                $this->getDestination(),
                $verify
            );
        }
    }

    /**
     * Write to file
     *
     * @param string $filename
     * @param bool $verify
     * @throws Exception
     */
    protected function writeToFile(string $filename, bool $verify): void
    {
        $outputFile = fopen($filename, $verify ? 'w+' : 'w');
        if($outputFile === false){
            throw new Exception(
                sprintf('Failed to open region output file %s', $filename)
            );
        }
        $chunkTable = [];
        $timestampTable = [];

        $writtenBytes = fwrite(
            $outputFile,
            str_pad(
                '',
                8 * 1024,
                pack('C', 0)
            )
        );
        if($writtenBytes !== 8 * 1024){
            throw new Exception(
                sprintf('Failed to write to region output file %s', $filename)
            );
        }

        foreach ($this->chunks as $chunk) {
            if ($chunk === null || !$chunk->isSaved()) {
                $chunkTable[] = pack('N', 0);
                $timestampTable[] = pack('N', 0);
                continue;
            }

            if(fseek($this->file, $chunk->getOffset() + 5) !== 0){
                throw new Exception("Failed to read region file: fseek failed");
            }

            $offset = ftell($outputFile);
            if($offset === false || $offset % 4096 !== 0){
                throw new Exception("Invalid offset in region file");
            }

            $size = $chunk->getLength();
            $padding = 4096 - ($size % 4096);
            $padding = ($padding === 4096 ? 0 : $padding);

            $value = ((int)($offset / 4096) << 8) | ((int)(($size + $padding) / 4096) & 0xFF);
            $chunkTable[] = pack('N', $value);

            $timestampTable[] = pack('N', $chunk->getTimestamp());

            $writtenBytes = fwrite(
                $outputFile,
                pack(
                    'NC',
                    $size - 4,
                    $chunk->getCompression()
                )
            );
            if($writtenBytes !== 5){
                throw new Exception(
                    sprintf('Failed to save region file to %s: chunk header write failed', $filename)
                );
            }

            $copyLength = $size - 5;
            if($verify){
                $data = $copyLength !== 0 ? fread($this->file, $copyLength) : "";
                if($data === false || strlen($data) !== $copyLength){
                    throw new Exception(
                        sprintf('Failed to save region file to %s: chunk read failed', $filename)
                    );
                }
                if(fwrite($outputFile, $data, $copyLength) !== $copyLength){
                    throw new Exception(
                        sprintf('Failed to save region file to %s: chunk write failed', $filename)
                    );
                }
                if(fseek($outputFile, -$copyLength, SEEK_CUR) !== 0){
                    throw new Exception(
                        sprintf('Failed to save region file to %s: fseek failed', $filename)
                    );
                }
                $checkData = $copyLength !== 0 ? fread($outputFile, $copyLength) : "";
                if($checkData === false || crc32($data) !== crc32($checkData)){
                    throw new Exception(
                        sprintf('Failed to save region file to %s: chunk checksum failed', $filename)
                    );
                }
            }else{
                if(stream_copy_to_stream($this->file, $outputFile, $copyLength) !== $copyLength){
                    throw new Exception(
                        sprintf('Failed to save region file to %s: failed to copy chunk', $filename)
                    );
                }
            }

            fwrite(
                $outputFile,
                str_pad(
                    '',
                    $padding,
                    pack('C', 0)
                )
            );

            $endOffset = ftell($outputFile);
            if ($endOffset === false || $endOffset % 4096 !== 0) {
                throw new Exception(
                    sprintf('Failed to save region file to %s', $filename)
                );
            }
        }

        if(fseek($outputFile, 0) !== 0){
            throw new Exception(
                sprintf('Failed to save region file to %s: fseek failed', $filename)
            );
        }

        $chunkTableStr = implode('', $chunkTable);
        if(fwrite($outputFile, $chunkTableStr, strlen($chunkTableStr)) !== strlen($chunkTableStr)){
            throw new Exception(
                sprintf('Failed to save region file to %s: failed to write chunk table', $filename)
            );
        }
        $timestampTableStr = implode('', $timestampTable);
        if(fwrite($outputFile, $timestampTableStr, strlen($timestampTableStr)) !== strlen($timestampTableStr)){
            throw new Exception(
                sprintf('Failed to save region file to %s: failed to write timestamp table', $filename)
            );
        }

        if(!fclose($outputFile)){
            throw new Exception(
                sprintf('Failed to save region file to %s: failed to close file', $filename)
            );
        }
    }

    protected function hasExistingChunks(): bool
    {
        $has = false;
        foreach ($this->chunks as $chunk) {
            if ($chunk !== null && $chunk->isSaved()) {
                $has = true;
                break;
            }
        }

        return $has;
    }

    /**
     * Count elements of an object
     * @link https://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     *
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count(): int
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
        $keys = array_keys($this->chunks, null);

        return count($keys) === 0;
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

    /**
     * @return int
     */
    public function getXPos(): int
    {
        return $this->xPos;
    }

    /**
     * @return int
     */
    public function getYPos(): int
    {
        return $this->yPos;
    }
}
