<?php

namespace Aternos\Thanos\Tests\Mca\Entry;

use Aternos\IO\Exception\IOException;
use Aternos\IO\System\File\TempMemoryFile;
use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\Mca\Entry\CompressionMethod;
use Aternos\Thanos\Mca\Entry\EntryHeader;
use Aternos\Thanos\Tests\ThanosTestCase;

class EntryHeaderTest extends ThanosTestCase
{
    public function testParseHeader(): void
    {
        $data = $this->makeEntryHeader(56, CompressionMethod::GZIP);
        $file = $this->getDataFile($data);

        $header = EntryHeader::load($file);
        $this->assertEquals(56, $header->getLength());
        $this->assertEquals(CompressionMethod::GZIP, $header->getCompressionMethod());
        $this->assertEquals(55, $header->getDataLength());
    }

    public function testParseHeaderWithCustomCompression(): void
    {
        $data = $this->makeEntryHeader(56, CompressionMethod::CUSTOM, "test:test");
        $file = $this->getDataFile($data);

        $header = EntryHeader::load($file);
        $this->assertEquals(56, $header->getLength());
        $this->assertEquals(CompressionMethod::CUSTOM, $header->getCompressionMethod());
        $this->assertEquals("test:test", $header->getCustomCompressionMethod());
        $this->assertEquals(44, $header->getDataLength());
    }

    public function testHeaderReadError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["read"]);
        $mock->expects($this->once())
            ->method("read")
            ->willThrowException(new IOException("IO error"));

        $this->expectExceptionMessage("Could not read entry header");
        $this->expectException(McaFileException::class);
        EntryHeader::load($mock);
    }

    public function testUnpackError(): void
    {
        $file = $this->getDataFile("bad");

        $this->expectExceptionMessage("Could not parse entry header");
        $this->expectException(McaFileException::class);
        EntryHeader::load($file);
    }

    public function testInvalidCompressionMethod(): void
    {
        $data = $this->makeEntryHeader(56, 99);
        $file = $this->getDataFile($data);

        $this->expectExceptionMessage("Unknown compression method: 99");
        $this->expectException(McaFileException::class);
        EntryHeader::load($file);
    }

    public function testCustomMethodParseError(): void
    {
        $data = $this->makeEntryHeader(56, CompressionMethod::CUSTOM);
        $file = $this->getDataFile($data . "a");

        $this->expectExceptionMessage("Could not read custom compression method length");
        $this->expectException(McaFileException::class);
        EntryHeader::load($file);
    }

    public function testCustomCompressionMethodUnexpectedEnd(): void
    {
        $data = $this->makeEntryHeader(56, CompressionMethod::CUSTOM, "test");
        $file = $this->getDataFile(substr($data, 0, -2));

        $this->expectExceptionMessage("Could not read full custom compression method");
        $this->expectException(McaFileException::class);
        EntryHeader::load($file);
    }

    public function testCustomCompressionMethodReadError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["read"]);
        $mock->expects($this->exactly(2))
            ->method("read")
            ->willReturnOnConsecutiveCalls(
                $this->makeEntryHeader(12, CompressionMethod::CUSTOM),
                $this->throwException(new IOException("IO error"))
            );

        $this->expectExceptionMessage("Could not read custom compression method");
        $this->expectException(McaFileException::class);
        EntryHeader::load($mock);
    }
}
