<?php

namespace Aternos\Thanos\Mca\Compression;

use Aternos\IO\Exception\IOException;
use Aternos\IO\Interfaces\Features\IsEndOfFileInterface;
use Aternos\IO\Interfaces\Features\ReadInterface;
use Aternos\IO\Interfaces\Features\SetPositionInterface;
use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\Exception\UnexpectedEndOfMcaFileException;
use Aternos\Thanos\Exception\UnsupportedFeatureException;
use Aternos\Thanos\Mca\Compression\LZ4\LZ4BlockHeader;
use Aternos\Thanos\Mca\Compression\LZ4\LZ4CompressionMethod;
use Exception;
use Generator;

class LZ4BlockMcaDataReader extends AbstractMcaDataReader
{
    /**
     * @inheritDoc
     */
    public function __construct(
        ReadInterface&IsEndOfFileInterface&SetPositionInterface $input,
        int                                                     $startOffset,
        int                                                     $length
    )
    {
        parent::__construct($input, $startOffset, $length);
        $this->checkLZ4Available();
    }

    /**
     * @return void
     * @codeCoverageIgnore cannot test missing extension
     */
    protected function checkLZ4Available(): void
    {
        if (!function_exists('lz4_uncompress')) {
            throw new UnsupportedFeatureException("ext-lz4 is required to read LZ4 compressed data");
        }
    }

    /**
     * @inheritDoc
     */
    public function read(): Generator
    {
        $offset = $this->startOffset;
        while ($offset < $this->startOffset + $this->length) {
            try {
                $eof = $this->input->isEndOfFile();
            } catch (IOException $e) {
                throw new McaFileException("Could not check end of MCA file", previous: $e);
            }
            if ($eof) {
                throw new UnexpectedEndOfMcaFileException("Unexpected end of MCA file");
            }

            try {
                $this->input->setPosition($offset);
            } catch (IOException $e) {
                throw new McaFileException("Could not set position in MCA file", previous: $e);
            }

            try {
                $headerData = $this->input->read(LZ4BlockHeader::HEADER_LENGTH);
            } catch (IOException $e) {
                throw new McaFileException("Could not read LZ4 block header data from MCA file", previous: $e);
            }
            try {
                $header = LZ4BlockHeader::load($headerData);
            } catch (Exception $e) {
                throw new McaFileException("Could not parse LZ4 block header", previous: $e);
            }

            $compressedLength = $header->getCompressedLength();
            try {
                $blockData = $this->input->read($compressedLength);
            } catch (IOException $e) {
                throw new McaFileException("Could not read LZ4 block data from MCA file", previous: $e);
            }

            $offset += LZ4BlockHeader::HEADER_LENGTH + $compressedLength;

            if ($header->getCompressionMethod() === LZ4CompressionMethod::RAW) {
                yield $blockData;
                continue;
            }

            error_clear_last();
            $result = @lz4_uncompress(pack("V", $header->getDecompressedLength()) . $blockData);
            if ($result === false) {
                $error = error_get_last();
                $message = 'unknown error';
                if ($error !== null && isset($error['message'])) {
                    $message = $error['message'];
                }
                throw new McaFileException("Could not uncompress LZ4 block data: " . $message);
            }

            // https://github.com/lz4/lz4-java/blob/master/src/java/net/jpountz/lz4/LZ4BlockOutputStream.java#L125
            $hash = hash("xxh32", $result, true, ["seed" => LZ4BlockHeader::XXHASH_SEED]);

            // https://github.com/lz4/lz4-java/blob/master/src/java/net/jpountz/xxhash/StreamingXXHash32.java#L101
            $checksum = unpack("V", strrev($hash))[1] & 0xFFFFFFF;

            if ($checksum !== $header->getChecksum()) {
                throw new McaFileException("LZ4 block checksum mismatch.");
            }

            yield $result;
        }
    }
}
