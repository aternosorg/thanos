<?php

namespace Aternos\Thanos\Reader;

use Aternos\Thanos\Reader\LZ4\BlockHeader;

class LZ4BlockReader extends BufferedReader
{
    public function __construct($resource, int $offset, int $length)
    {
        parent::__construct($resource, $offset, $length);

        if (!function_exists("lz4_uncompress")) {
            throw new \Exception("ext-lz4 is required to read LZ4 compressed data.");
        }
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    protected function getRawChunk(int $length): string
    {
        $header = BlockHeader::load($this->readRaw(BlockHeader::HEADER_LENGTH));

        $compressedLength = $header->getCompressedLength();
        $chunk = $this->readRaw($compressedLength);
        if (strlen($chunk) !== $compressedLength) {
            throw new \Exception("Could not read compressed chunk data.");
        }

        if ($header->getCompressionMethod() === BlockHeader::COMPRESSION_METHOD_RAW) {
            return $chunk;
        }

        $result = lz4_uncompress(pack("V", $header->getDecompressedLength()) . $chunk);
        if ($result === false) {
            throw new \Exception("Could not uncompress chunk data.");
        }

        // https://github.com/lz4/lz4-java/blob/master/src/java/net/jpountz/lz4/LZ4BlockOutputStream.java#L125
        $hash = hash("xxh32", $result, true, ["seed" => BlockHeader::XXHASH_SEED]);

        // https://github.com/lz4/lz4-java/blob/master/src/java/net/jpountz/xxhash/StreamingXXHash32.java#L101
        $checksum = unpack("V", strrev($hash))[1] & 0xFFFFFFF;

        if ($checksum !== $header->getChecksum()) {
            throw new \Exception("Checksum mismatch.");
        }

        return $result;
    }
}
