<?php

namespace Aternos\Thanos\Mca\Compression\LZ4;

use Aternos\Thanos\Exception\LZ4DataException;
use Exception;

/**
 * https://github.com/lz4/lz4-java/blob/master/src/java/net/jpountz/lz4/LZ4BlockOutputStream.java
 */
class LZ4BlockHeader
{
    const int COMPRESSION_LEVEL_BASE = 10;
    const int XXHASH_SEED = 0x9747b28c;
    const string MAGIC = "LZ4Block";

    const int HEADER_LENGTH =
        8       // magic
        + 1     // token
        + 4     // compressed length
        + 4     // decompressed length
        + 4;    // checksum

    /**
     * @param string $data
     * @return static
     * @throws Exception
     */
    static function load(string $data): static
    {
        if (strlen($data) < static::HEADER_LENGTH) {
            throw new LZ4DataException("LZ4 block header is too short");
        }

        if (!str_starts_with($data, static::MAGIC)) {
            throw new LZ4DataException("LZ4 block header has invalid magic bytes");
        }

        $values = static::unpackHeader($data);

        $token = $values["token"];
        $compressionLevel = static::COMPRESSION_LEVEL_BASE + ($token & 0x0f);
        $compressionMethod = LZ4CompressionMethod::tryFrom($token & 0xf0);
        if ($compressionMethod === null) {
            throw new LZ4DataException("Invalid LZ4 block compression method");
        }

        return new static(
            $compressionMethod,
            $compressionLevel,
            $values["compressedLength"],
            $values["decompressedLength"],
            $values["checksum"]
        );
    }

    /**
     * @param string $data
     * @return array
     * @throws LZ4DataException
     */
    protected static function unpackHeader(string $data): array
    {
        $values = @unpack("Ctoken/VcompressedLength/VdecompressedLength/Vchecksum", $data, strlen(static::MAGIC));
        if ($values === false) {
            throw new LZ4DataException("Could not parse LZ4 block header");
        }
        return $values;
    }

    /**
     * @param LZ4CompressionMethod $compressionMethod
     * @param int $compressionLevel
     * @param int $compressedLength
     * @param int $decompressedLength
     * @param int $checksum
     */
    public function __construct(
        protected LZ4CompressionMethod $compressionMethod,
        protected int                  $compressionLevel,
        protected int                  $compressedLength,
        protected int                  $decompressedLength,
        protected int                  $checksum
    )
    {
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
     * @return LZ4CompressionMethod
     */
    public function getCompressionMethod(): LZ4CompressionMethod
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
