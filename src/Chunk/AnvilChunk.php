<?php

namespace Aternos\Thanos\Chunk;

use Aternos\Thanos\Reader\LZ4BlockReader;
use Aternos\Thanos\Reader\RawReader;
use Aternos\Thanos\Reader\ReaderInterface;
use Aternos\Thanos\Reader\ZlibReader;
use Exception;

/**
 * Class AnvilChunk
 * Object representing a Minecraft Anvil world chunk
 *
 * @package Aternos\Thanos\Chunk
 */
class AnvilChunk implements ChunkInterface
{

    /**
     * @var resource
     */
    protected $file;

    /**
     * @var int
     */
    protected int $offset;

    /**
     * @var int
     */
    protected int $dataOffset;

    /**
     * @var int
     */
    protected int $length;

    /**
     * @var int
     */
    protected int $dataLength;

    /**
     * @var int
     */
    protected int $compression;

    /**
     * @var int|null
     */
    protected ?int $timestamp = null;

    /**
     * @var int|null
     */
    protected ?int $inhabitedTime = null;

    /**
     * @var int|null
     */
    protected ?int $lastUpdate = null;

    /**
     * @var ReaderInterface
     */
    protected ReaderInterface $reader;

    /**
     * @var bool
     */
    protected bool $saved = false;

    /**
     * @var int
     */
    protected int $regionFileIndex;

    /**
     * @var int
     */
    protected int $xPos;

    /**
     * @var int
     */
    protected int $yPos;

    /**
     * @var int[]
     */
    protected array $regionPosition;

    /**
     * AnvilChunk constructor.
     *
     * @param resource $file
     * @param int $offset
     * @param int[] $regionPosition
     * @param int $regionFileIndex
     * @throws Exception
     */
    public function __construct($file, int $offset, array $regionPosition, int $regionFileIndex)
    {
        $this->file = $file;
        fseek($this->file, $offset);
        $this->offset = $offset;
        $this->dataOffset = $offset + 5;
        $this->regionFileIndex = $regionFileIndex;
        $this->regionPosition = $regionPosition;

        $this->xPos = ($this->regionFileIndex % 32) - 1;
        $this->yPos = intdiv($this->regionFileIndex, 32);

        $this->readHeader();

        $this->reader = $this->createReader();
    }

    /**
     * @return ReaderInterface
     * @throws Exception
     */
    protected function createReader(): ReaderInterface
    {
        return match ($this->compression) {
            1 => new ZlibReader(
                $this->file,
                ZLIB_ENCODING_GZIP,
                $this->dataOffset,
                $this->dataLength
            ),
            2 => new ZlibReader(
                $this->file,
                ZLIB_ENCODING_DEFLATE,
                $this->dataOffset,
                $this->dataLength
            ),
            3 => new RawReader(
                $this->file,
                $this->dataOffset,
                $this->dataLength
            ),
            4 => new LZ4BlockReader(
                $this->file,
                $this->dataOffset,
                $this->dataLength
            ),
            127 => $this->getCustomReader($this->readCustomCompressionName()),
            default => throw new Exception("Unknown chunk compression type."),
        };
    }

    /**
     * @param string $name
     * @return ReaderInterface
     * @throws Exception
     */
    protected function getCustomReader(string $name): ReaderInterface
    {
        throw new Exception("Unsupported custom compression type: " . $name);
    }

    /**
     * Read chunk header
     *
     * @throws Exception
     */
    protected function readHeader(): void
    {
        $rawValue = unpack('N', fread($this->file, 4));
        if ($rawValue === false) {
            throw new Exception("Failed to read chunk length.");
        }
        $this->length = $rawValue['1'] + 4;
        $this->dataLength = $this->length - 5;

        $rawValue = unpack('C', fread($this->file, 1));
        if ($rawValue === false) {
            throw new Exception("Failed to read chunk compression.");
        }
        $this->compression = $rawValue['1'];
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function readCustomCompressionName(): string
    {
        $name = stream_get_line($this->file, $this->dataLength, "\x00");
        if ($name === false) {
            throw new Exception("Failed to read custom compression name.");
        }
        return $name;
    }

    /**
     * Get offset of chunk data within the region file
     *
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Get length of chunk data
     *
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * Get InhabitedTime
     * Returns -1 if InhabitedTime could not be read
     *
     * @return int
     * @throws Exception
     */
    public function getInhabitedTime(): int
    {
        if ($this->inhabitedTime === null) {
            $this->reader->rewind();
            $data = $this->readAfter(hex2bin('04000D') . 'InhabitedTime', 8);
            $rawData = $data !== null ? unpack('J', $data) : false;
            if ($rawData === false) {
                return -1;
            }
            $this->inhabitedTime = $rawData['1'];
        }

        return $this->inhabitedTime;
    }

    /**
     * Read $length bytes after $str
     *
     * @param string $str
     * @param int $length
     * @param int $limit
     * @return string|null
     * @throws Exception
     */
    protected function readAfter(
        string $str,
        int    $length,
        int    $limit = 1024 * 1024 * 10
    ): ?string
    {
        $startPointer = $this->reader->tell();
        $strPointer = 0;
        $valuePos = -1;
        while (
            !$this->reader->eof()
            && $this->reader->tell() < $startPointer + $limit
        ) {
            $data = $this->reader->read(2048);
            $dataStart = $this->reader->tell() - strlen($data);
            $pos = strpos($data, $str);
            if ($pos !== false) {
                $valuePos = $dataStart + $pos + strlen($str);
                break;
            }
            for ($i = 0; $i < strlen($data); $i++) {
                if ($data[$i] === $str[$strPointer]) {
                    $strPointer++;
                    if ($strPointer === strlen($str)) {
                        $valuePos = $dataStart + $i;
                        break 2;
                    }
                } else {
                    $strPointer = 0;
                }
            }
        }
        if ($valuePos === -1) {
            return null;
        }
        $this->reader->seek($valuePos);
        return $this->reader->read($length);
    }

    /**
     * Set last modified time
     *
     * @param int $timestamp
     */
    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * Get last modified time
     *
     * @return int
     */
    public function getTimestamp(): int
    {
        if ($this->timestamp === null) {
            $this->timestamp = time();
        }

        return $this->timestamp;
    }

    /**
     * Get compression type (1: gzip, 2: zlib)
     *
     * @return int
     */
    public function getCompression(): int
    {
        return $this->compression;
    }

    /**
     * Save this chunk
     *
     */
    public function save(): void
    {
        $this->saved = true;
    }

    /**
     * Check if this chunk is saved
     *
     * @return bool
     */
    public function isSaved(): bool
    {
        return $this->saved;
    }

    /**
     * Get time of last chunk update
     *
     * @return int
     * @throws Exception
     */
    public function getLastUpdate(): int
    {
        if ($this->lastUpdate === null) {
            $this->reader->rewind();
            $data = $this->readAfter(hex2bin('04000A') . 'LastUpdate', 8);
            $rawData = $data !== null ? unpack('J', $data) : false;
            if ($rawData === false) {
                return -1;
            }
            $this->lastUpdate = $rawData['1'];
        }

        return $this->lastUpdate;
    }

    /**
     * Get the raw chunk data
     *
     * @return string
     */
    public function getData(): string
    {
        fseek($this->file, $this->offset);

        return fread($this->file, $this->length);
    }

    /**
     * @return int
     */
    public function getRegionXPos(): int
    {
        return $this->xPos;
    }

    /**
     * @return int
     */
    public function getRegionYPos(): int
    {
        return $this->yPos;
    }

    /**
     * @inheritDoc
     */
    public function getGlobalXPos(): int
    {
        return $this->regionPosition[0] * 32 + $this->xPos;
    }

    /**
     * @inheritDoc
     */
    public function getGlobalYPos(): int
    {
        return $this->regionPosition[1] * 32 + $this->yPos;
    }

    /**
     * @return void
     */
    public function close(): void
    {
        $this->reader->reset();
    }
}
