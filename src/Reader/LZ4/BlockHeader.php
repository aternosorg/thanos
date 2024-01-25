<?php

namespace Aternos\Thanos\Reader\LZ4;

use Exception;

/**
 * https://github.com/lz4/lz4-java/blob/master/src/java/net/jpountz/lz4/LZ4BlockOutputStream.java
 */
class BlockHeader
{
    const COMPRESSION_LEVEL_BASE = 10;
    const COMPRESSION_METHOD_RAW = 0x10;
    const COMPRESSION_METHOD_LZ4 = 0x20;
    const XXHASH_SEED = 0x9747b28c;
    const MAGIC = "LZ4Block";

    const HEADER_LENGTH =
        8       // magic
        + 1     // token
        + 4     // compressed length
        + 4     // decompressed length
        + 4;    // checksum

    protected int $token;
    protected int $compressionMethod;
    protected int $compressionLevel;
    protected int $compressedLength;
    protected int $decompressedLength;
    protected int $checksum;

    /**
     * @param string $data
     * @return static
     * @throws Exception
     */
    static function load(string $data): static
    {
        if (strlen($data) < static::HEADER_LENGTH) {
            throw new Exception("Invalid LZ4 block header");
        }

        if (!str_starts_with($data, static::MAGIC)) {
            throw new Exception("Invalid LZ4 block header");
        }

        $values = unpack("Ctoken/VcompressedLength/VdecompressedLength/Vchecksum", $data, strlen(static::MAGIC));

        return new static($values["token"], $values["compressedLength"], $values["decompressedLength"], $values["checksum"]);
    }

    /**
     * @param int $token
     * @param int $compressedLength
     * @param int $decompressedLength
     * @param int $checksum
     * @throws Exception
     */
    public function __construct(int $token, int $compressedLength, int $decompressedLength, int $checksum)
    {
        $this->token = $token;
        $this->compressedLength = $compressedLength;
        $this->decompressedLength = $decompressedLength;
        $this->checksum = $checksum;

        $this->compressionMethod = $this->token & 0xf0;
        $this->compressionLevel = static::COMPRESSION_LEVEL_BASE + ($this->token & 0x0f);

        if (!in_array($this->compressionMethod, [static::COMPRESSION_METHOD_LZ4, static::COMPRESSION_METHOD_RAW])) {
            throw new Exception("Invalid LZ4 block compression method");
        }
    }

    /**
     * @return int
     */
    public function getToken(): int
    {
        return $this->token;
    }

    /**
     * @return int
     */
    public function getCompressedLength(): int
    {
        return $this->compressedLength;
    }

    /**
     * @return int
     */
    public function getDecompressedLength(): int
    {
        return $this->decompressedLength;
    }

    /**
     * @return int
     */
    public function getChecksum(): int
    {
        return $this->checksum;
    }

    /**
     * @return int
     */
    public function getCompressionMethod(): int
    {
        return $this->compressionMethod;
    }

    /**
     * @return int
     */
    public function getCompressionLevel(): int
    {
        return $this->compressionLevel;
    }
}
