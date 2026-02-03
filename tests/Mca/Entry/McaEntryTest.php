<?php

namespace Aternos\Thanos\Tests\Mca\Entry;

use Aternos\IO\Exception\IOException;
use Aternos\IO\System\File\TempMemoryFile;
use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\Exception\UnsupportedFeatureException;
use Aternos\Thanos\Mca\Compression\LZ4BlockMcaDataReader;
use Aternos\Thanos\Mca\Compression\RawMcaDataReader;
use Aternos\Thanos\Mca\Compression\ZLibMcaDataReader;
use Aternos\Thanos\Mca\Entry\CompressionMethod;
use Aternos\Thanos\Mca\Entry\EntryHeader;
use Aternos\Thanos\Mca\Entry\McaEntry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionClass;

class McaEntryTest extends McaEntryTestCase
{
    #[TestWith([CompressionMethod::GZIP, ZLibMcaDataReader::class])]
    #[TestWith([CompressionMethod::ZLIB, ZLibMcaDataReader::class])]
    #[TestWith([CompressionMethod::RAW, RawMcaDataReader::class])]
    #[TestWith([CompressionMethod::LZ4, LZ4BlockMcaDataReader::class])]
    public function testGetReader(CompressionMethod $method, string $readerClass): void
    {
        if ($method === CompressionMethod::LZ4 && !function_exists('lz4_uncompress')) {
            $this->markTestSkipped("LZ4 extension is not available");
        }

        $data = $this->getDataFile("");
        $entry = new McaEntry($data, 0, 0, 0, 0);
        $reflection = new ReflectionClass($entry);
        $header = new EntryHeader(0, $method);

        $reader = $reflection->getMethod("getReader")->invoke($entry, $header);
        $this->assertInstanceOf($readerClass, $reader);
    }

    public function testGetCustomReader(): void
    {
        $data = $this->getDataFile("");
        $entry = new McaEntry($data, 0, 0, 0, 0);
        $reflection = new ReflectionClass($entry);
        $header = new EntryHeader(0, CompressionMethod::CUSTOM, "test:test");

        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessage("Custom compression method 'test:test' is not supported");
        $reflection->getMethod("getReader")->invoke($entry, $header);
    }

    public function testGetHeader(): void
    {
        $data = $this->getDataFile($this->makeEntryHeader(4, CompressionMethod::RAW));
        $entry = new McaEntry($data, 0, 4096, 0, 0);
        $reflection = new ReflectionClass($entry);

        $header = $reflection->getMethod("getHeader")->invoke($entry);
        $this->assertInstanceOf(EntryHeader::class, $header);
        $this->assertEquals(4, $header->getLength());
        $this->assertEquals(3, $header->getDataLength());
        $this->assertEquals(CompressionMethod::RAW, $header->getCompressionMethod());
    }

    public function testGetHeaderSeekError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["setPosition"]);
        $mock->expects($this->once())
            ->method("setPosition")
            ->willThrowException(new IOException("IO error"));
        $entry = new McaEntry($mock, 0, 4096, 0, 0);
        $reflection = new ReflectionClass($entry);

        $this->expectException(McaFileException::class);
        $this->expectExceptionMessage("Failed to seek to entry header");
        $reflection->getMethod("getHeader")->invoke($entry);
    }

    public function testGetData(): void
    {
        $data = $this->getDataFile($this->makeEntryHeader(5, CompressionMethod::RAW) . "data");
        $entry = new McaEntry($data, 0, 4096, 0, 0);

        $result = "";
        foreach ($entry->getData() as $chunk) {
            $result .= $chunk;
        }

        $this->assertEquals("data", $result);
    }

    public function testGetAllData(): void
    {
        $data = $this->getDataFile($this->makeEntryHeader(5, CompressionMethod::RAW) . "data");
        $entry = new McaEntry($data, 0, 4096, 0, 0);

        $result = $entry->getAllData();

        $this->assertEquals("data", $result);
    }

    public function testGetSerializedData(): void
    {
        $content = $this->makeEntryHeader(5, CompressionMethod::RAW) . "data";
        $data = $this->getDataFile($content);
        $entry = new McaEntry($data, 0, 4096, 0, 0);

        $result = "";
        foreach ($entry->getSerializedData() as $chunk) {
            $result .= $chunk;
        }

        $this->assertEquals($content, $result);
    }

    public function testGetSerializedDataSeekError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["setPosition"]);
        $mock->expects($this->once())
            ->method("setPosition")
            ->willThrowException(new IOException("IO error"));
        $entry = new McaEntry($mock, 0, 4096, 0, 0);
        $reflection = new ReflectionClass($entry);
        $reflection->getProperty("header")->setValue($entry, new EntryHeader(5, CompressionMethod::RAW));

        $this->expectException(McaFileException::class);
        $this->expectExceptionMessage("Could not set position in MCA file");
        foreach ($entry->getSerializedData() as $chunk) {
            // Do nothing
        }
    }

    public function testGetSerializedDataEofCheckError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["isEndOfFile"]);
        $mock->expects($this->once())
            ->method("isEndOfFile")
            ->willThrowException(new IOException("IO error"));
        $entry = new McaEntry($mock, 0, 4096, 0, 0);
        $reflection = new ReflectionClass($entry);
        $reflection->getProperty("header")->setValue($entry, new EntryHeader(5, CompressionMethod::RAW));

        $this->expectException(McaFileException::class);
        $this->expectExceptionMessage("Could not check end of MCA file");
        foreach ($entry->getSerializedData() as $chunk) {
            // Do nothing
        }
    }

    public function testGetSerializedDataUnexpectedEof(): void
    {
        $content = $this->makeEntryHeader(8, CompressionMethod::RAW) . "data";
        $data = $this->getDataFile($content);
        $entry = new McaEntry($data, 0, 4096, 0, 0);

        $this->expectException(McaFileException::class);
        $this->expectExceptionMessage("Unexpected end of MCA file");
        foreach ($entry->getSerializedData() as $chunk) {
            // Do nothing
        }
    }

    public function testGetSerializedDataReadError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["read"]);
        $mock->expects($this->once())
            ->method("read")
            ->willThrowException(new IOException("IO error"));
        $entry = new McaEntry($mock, 0, 4096, 0, 0);
        $reflection = new ReflectionClass($entry);
        $reflection->getProperty("header")->setValue($entry, new EntryHeader(5, CompressionMethod::RAW));

        $this->expectException(McaFileException::class);
        $this->expectExceptionMessage("Could not read data from MCA file");
        foreach ($entry->getSerializedData() as $chunk) {
            // Do nothing
        }
    }

    public function testGetters(): void
    {
        $data = $this->getDataFile("");
        $entry = new McaEntry($data, 1234, 4096, 2, 3);

        $this->assertEquals(3, $entry->getModifiedTime());
        $this->assertEquals(2, $entry->getRegionIndex());
        $this->assertEquals(2, $entry->getXPos());
        $this->assertEquals(0, $entry->getZPos());
    }

    public function testSetReadChunkSize(): void
    {
        $data = $this->getDataFile("");
        $entry = new McaEntry($data, 1234, 4096, 2, 3);

        $reflection = new ReflectionClass($entry);
        $property = $reflection->getProperty("readChunkSize");

        $entry->setReadChunkSize(8192);
        $this->assertEquals(8192, $property->getValue($entry));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Read chunk size must be greater than 1");
        $entry->setReadChunkSize(0);
    }

    public function testExternalEntry(): void
    {
        $data = $this->getDataFile($this->makeEntryHeader(1, CompressionMethod::EXTERNAL_GZIP));
        $entry = new McaEntry($data, 0, 4096, 0, 0);
        $reflection = new ReflectionClass($entry);
        $header = $reflection->getMethod("getHeader")->invoke($entry);

        $this->assertTrue($entry->isExternal());

        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessage("Unsupported compression method EXTERNAL_GZIP");
        $reflection->getMethod("getReader")->invoke($entry, $header);
    }
}
