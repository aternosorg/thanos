<?php

namespace Aternos\Thanos\Tests;

use Aternos\IO\System\Directory\Directory;
use Aternos\IO\System\File\TempMemoryFile;
use Aternos\Thanos\Mca\Compression\LZ4\LZ4BlockHeader;
use Aternos\Thanos\Mca\Compression\LZ4\LZ4CompressionMethod;
use Aternos\Thanos\Mca\Entry\CompressionMethod;
use PHPUnit\Framework\TestCase;

class ThanosTestCase extends TestCase
{
    const string TEST_WORLD = __DIR__ . "/Fixtures/world";
    const string TEST_WORLD_26_1 = __DIR__ . "/Fixtures/world-26-1";
    const string TEST_LEGACY_FORCELOAD = __DIR__ . "/Fixtures/legacy-forceload";
    const string TEST_REGION = self::TEST_WORLD . "/region/r.0.0.mca";
    const string TEST_ENTITIES = self::TEST_WORLD . "/entities/r.0.0.mca";
    const string TEST_POI = self::TEST_WORLD . "/poi/r.0.0.mca";
    const string TEST_DATA = __DIR__ . "/data/";

    protected function setUp(): void
    {
        parent::setUp();
        mkdir(static::TEST_DATA);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        new Directory(static::TEST_DATA)->delete();
    }

    protected function getDataFile(string $data, ?TempMemoryFile $file = null): TempMemoryFile
    {
        $file ??= new TempMemoryFile();
        $file->write($data);
        $file->setPosition(0);
        return $file;
    }

    protected function makeLZ4BlockHeader(
        LZ4CompressionMethod $method,
        int                  $compressionLevel,
        int                  $compressedLength,
        int                  $decompressedLength,
        int                  $checksum
    ): string
    {
        $token = $method->value | $compressionLevel - LZ4BlockHeader::COMPRESSION_LEVEL_BASE;
        return LZ4BlockHeader::MAGIC . pack("CVVV", $token, $compressedLength, $decompressedLength, $checksum);
    }

    /**
     * @param string $data
     * @return int
     */
    protected function makeLZ4Checksum(string $data): int
    {
        // https://github.com/lz4/lz4-java/blob/master/src/java/net/jpountz/lz4/LZ4BlockOutputStream.java#L125
        $hash = hash("xxh32", $data, true, ["seed" => LZ4BlockHeader::XXHASH_SEED]);

        // https://github.com/lz4/lz4-java/blob/master/src/java/net/jpountz/xxhash/StreamingXXHash32.java#L101
        return unpack("V", strrev($hash))[1] & 0xFFFFFFF;
    }

    protected function makeLZ4Block(
        string               $data,
        LZ4CompressionMethod $method = LZ4CompressionMethod::LZ4,
        array                $headerOverrides = []
    ): string
    {
        $checksum = $this->makeLZ4Checksum($data);
        $uncompressedLength = strlen($data);
        if ($method === LZ4CompressionMethod::LZ4) {
            $compressedData = substr(lz4_compress($data, 11), 4); // Remove the 4-byte length prefix
        } else {
            $compressedData = $data;
        }

        $compressedLength = strlen($compressedData);
        $header = $this->makeLZ4BlockHeader(
            $headerOverrides["method"] ?? $method,
            $headerOverrides["compressionLevel"] ?? 11,
            $headerOverrides["compressedLength"] ?? $compressedLength,
            $headerOverrides["headerOverrides"] ?? $uncompressedLength,
            $headerOverrides["checksum"] ?? $checksum
        );

        return $header . $compressedData;
    }

    protected function makeEntryHeader(
        int $length,
        CompressionMethod|int $compressionMethod,
        ?string $customCompressionMethod = null
    )
    {
        if ($compressionMethod instanceof CompressionMethod) {
            $compressionMethod = $compressionMethod->value;
        }

        $data = pack("NC", $length, $compressionMethod);
        if ($compressionMethod === CompressionMethod::CUSTOM->value && $customCompressionMethod !== null) {
            $customMethodLength = strlen($customCompressionMethod);
            $data .= pack("n", $customMethodLength);
            $data .= $customCompressionMethod;
        }
        return $data;
    }

    /**
     * @param string $content
     * @return string
     */
    protected function makeEntry(string $content): string
    {
        return $this->makeEntryHeader(strlen($content) + 1, CompressionMethod::RAW) . $content;
    }
}
