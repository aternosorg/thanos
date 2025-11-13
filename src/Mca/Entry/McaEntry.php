<?php

namespace Aternos\Thanos\Mca\Entry;

use Aternos\IO\Exception\IOException;
use Aternos\IO\Interfaces\Features\IsEndOfFileInterface;
use Aternos\IO\Interfaces\Features\ReadInterface;
use Aternos\IO\Interfaces\Features\SetPositionInterface;
use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\Exception\UnsupportedFeatureException;
use Aternos\Thanos\Mca\Compression\LZ4BlockMcaDataReader;
use Aternos\Thanos\Mca\Compression\McaDataReaderInterface;
use Aternos\Thanos\Mca\Compression\RawMcaDataReader;
use Aternos\Thanos\Mca\Compression\ZLibMcaDataReader;
use Generator;

class McaEntry implements McaEntryInterface
{
    protected const int CHUNK_SIZE = 16 * 1024;

    protected ?EntryHeader $header = null;

    /**
     * @param ReadInterface&IsEndOfFileInterface&SetPositionInterface $input
     * @param int $startOffset
     * @param int $length
     * @param int $regionIndex
     * @param int $modifiedTime
     */
    public function __construct(
        protected ReadInterface&SetPositionInterface&IsEndOfFileInterface $input,
        protected int                                                     $startOffset,
        protected int                                                     $length,
        protected int                                                     $regionIndex,
        protected int                                                     $modifiedTime,
    )
    {
    }

    /**
     * @return EntryHeader
     * @throws McaFileException
     */
    protected function getHeader(): EntryHeader
    {
        if ($this->header === null) {
            try {
                $this->input->setPosition($this->startOffset);
            } catch (IOException $e) {
                throw new McaFileException("Failed to seek to entry header", previous: $e);
            }
            $this->header = EntryHeader::load($this->input);
        }
        return $this->header;
    }

    /**
     * @param EntryHeader $header
     * @return McaDataReaderInterface
     */
    protected function getReader(EntryHeader $header): McaDataReaderInterface
    {
        $startOffset = $this->startOffset + EntryHeader::HEADER_LENGTH;
        $dataLength = $header->getDataLength();
        return match ($header->getCompressionMethod()) {
            CompressionMethod::GZIP => new ZLibMcaDataReader(
                $this->input,
                $startOffset,
                $dataLength,
                static::CHUNK_SIZE,
                ZLIB_ENCODING_GZIP
            ),
            CompressionMethod::ZLIB => new ZLibMcaDataReader(
                $this->input,
                $startOffset,
                $dataLength,
                static::CHUNK_SIZE,
                ZLIB_ENCODING_DEFLATE
            ),
            CompressionMethod::RAW => new RawMcaDataReader(
                $this->input,
                $startOffset,
                $dataLength,
                static::CHUNK_SIZE
            ),
            CompressionMethod::LZ4 => new LZ4BlockMcaDataReader(
                $this->input,
                $startOffset,
                $dataLength
            ),
            CompressionMethod::CUSTOM => throw new UnsupportedFeatureException("Custom compression method '" . $header->getCustomCompressionMethod() . "' is not supported"),
        };
    }

    /**
     * @inheritDoc
     */
    public function getData(): Generator
    {
        $header = $this->getHeader();
        $reader = $this->getReader($header);
        yield from $reader->read();
    }

    /**
     * @inheritDoc
     */
    public function getSerializedData(): Generator
    {
        $header = $this->getHeader();

        try {
            $this->input->setPosition($this->startOffset);
        } catch (IOException $e) {
            throw new McaFileException("Could not set position in MCA file", previous: $e);
        }
        $offset = $this->startOffset;
        $endOffset = $this->startOffset + $header->getLength() + 4;

        while ($offset < $endOffset) {
            try {
                $eof = $this->input->isEndOfFile();
            } catch (IOException $e) {
                throw new McaFileException("Could not check end of MCA file", previous: $e);
            }
            if ($eof) {
                throw new McaFileException("Unexpected end of MCA file");
            }

            $toRead = min(self::CHUNK_SIZE, $endOffset - $offset);
            try {
                $data = $this->input->read($toRead);
            } catch (IOException $e) {
                throw new McaFileException("Could not read data from MCA file", previous: $e);
            }

            yield $data;
            $offset += strlen($data);
        }
    }

    /**
     * @inheritDoc
     */
    public function getModifiedTime(): int
    {
        return $this->modifiedTime;
    }

    /**
     * @inheritDoc
     */
    public function getRegionIndex(): int
    {
        return $this->regionIndex;
    }

    /**
     * @inheritDoc
     */
    public function getXPos(): int
    {
        return $this->regionIndex % 32;
    }

    /**
     * @inheritDoc
     */
    public function getZPos(): int
    {
        return intdiv($this->regionIndex, 32);
    }
}
