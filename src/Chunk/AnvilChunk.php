<?php

namespace Aternos\Thanos\Chunk;

use Aternos\Thanos\Reader\ZlibReader;

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
    protected $offset;

    /**
     * @var int
     */
    protected $dataOffset;

    /**
     * @var int
     */
    protected $length;

    /**
     * @var int
     */
    protected $compression;

    /**
     * @var int
     */
    protected $timestamp = null;

    /**
     * @var int
     */
    protected $inhabitedTime;

    /**
     * @var int
     */
    protected $lastUpdate;

    /**
     * @var ZlibReader
     */
    protected $zlibReader;

    /**
     * @var bool
     */
    protected $saved = false;

    /**
     * AnvilChunk constructor.
     *
     * @param resource $file
     * @param int $offset
     */
    public function __construct($file, int $offset)
    {
        $this->file = $file;
        fseek($this->file, $offset);
        $this->offset = $offset;
        $this->dataOffset = $offset + 5;

        $this->readHeader();
        $this->zlibReader = new ZlibReader(
            $this->file,
            $this->compression === 1 ? ZLIB_ENCODING_GZIP : ZLIB_ENCODING_DEFLATE,
            $this->dataOffset,
            $this->length - 5
        );
    }

    /**
     * Read chunk header
     *
     */
    protected function readHeader(): void
    {
        $rawValue = unpack('N', fread($this->file, 4));
        $this->length = $rawValue['1'] + 4;

        $rawValue = unpack('C', fread($this->file, 1));
        $this->compression = $rawValue['1'];
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
     */
    public function getInhabitedTime(): int
    {
        if ($this->inhabitedTime === null) {
            $this->zlibReader->rewind();
            $data = $this->readAfter(hex2bin('04000D') . 'InhabitedTime', 8);
            $this->inhabitedTime = $data !== null ? unpack('J', $data)['1'] : -1;
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
     */
    protected function readAfter(
        string $str,
        int $length,
        int $limit = 8192
    ): ?string {
        $startPointer = $this->zlibReader->tell();
        $strPointer = 0;
        $valuePos = -1;
        while (
            !$this->zlibReader->eof()
            && $this->zlibReader->tell() < $startPointer + $limit
        ) {
            $data = $this->zlibReader->read(512);
            $dataStart = $this->zlibReader->tell() - strlen($data);
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
        $this->zlibReader->seek($valuePos);
        return $this->zlibReader->read($length);
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
     */
    public function getLastUpdate(): int
    {
        if ($this->lastUpdate === null) {
            $this->zlibReader->rewind();
            $data = $this->readAfter(hex2bin('04000A') . 'LastUpdate', 8);
            $this->lastUpdate = $data !== null ? unpack('J', $data)['1'] : -1;
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
}
