<?php

namespace Aternos\Thanos\Tests\Mca\Compression\LZ4;

use Aternos\Thanos\Exception\LZ4DataException;
use Aternos\Thanos\Mca\Compression\LZ4\LZ4BlockHeader;
use Aternos\Thanos\Mca\Compression\LZ4\LZ4CompressionMethod;
use Aternos\Thanos\Tests\Mca\Compression\LZ4TestCase;
use Aternos\Thanos\Tests\Mca\Compression\ReaderTestCase;
use ReflectionClass;

class LZ4BlockHeaderTest extends LZ4TestCase
{
    public function testParseBlockHeader(): void
    {
        $data = $this->makeLZ4BlockHeader(
            LZ4CompressionMethod::LZ4,
            11,
            1234,
            4321,
            5678
        );

        $header = LZ4BlockHeader::load($data);
        $this->assertEquals(LZ4CompressionMethod::LZ4, $header->getCompressionMethod());
        $this->assertEquals(11, $header->getCompressionLevel());
        $this->assertEquals(1234, $header->getCompressedLength());
        $this->assertEquals(4321, $header->getDecompressedLength());
        $this->assertEquals(5678, $header->getChecksum());
    }

    public function testNotEnoughData(): void
    {
        $this->expectExceptionMessage("LZ4 block header is too short");
        $this->expectException(LZ4DataException::class);

        LZ4BlockHeader::load("short data");
    }

    public function testInvalidMagic(): void
    {
        $this->expectExceptionMessage("LZ4 block header has invalid magic bytes");
        $this->expectException(LZ4DataException::class);

        LZ4BlockHeader::load("Some data that does not start with magic");
    }

    public function testInvalidCompressionMethod(): void
    {
        $token = 0xff; // Invalid compression method
        $data = pack("CVVV", $token, 1234, 4321, 5678);

        $this->expectExceptionMessage("Invalid LZ4 block compression method");
        $this->expectException(LZ4DataException::class);

        LZ4BlockHeader::load(LZ4BlockHeader::MAGIC . $data);
    }

    public function testUnpackError(): void
    {
        $reflection = new ReflectionClass(LZ4BlockHeader::class);
        $method = $reflection->getMethod("unpackHeader");

        $this->expectException(LZ4DataException::class);
        $method->invoke(null, "invalid data");
    }

    public function testGetters(): void
    {
        $header = new LZ4BlockHeader(
            LZ4CompressionMethod::LZ4,
            12,
            2000,
            4000,
            8000
        );

        $this->assertEquals(LZ4CompressionMethod::LZ4, $header->getCompressionMethod());
        $this->assertEquals(12, $header->getCompressionLevel());
        $this->assertEquals(2000, $header->getCompressedLength());
        $this->assertEquals(4000, $header->getDecompressedLength());
        $this->assertEquals(8000, $header->getChecksum());
    }
}
