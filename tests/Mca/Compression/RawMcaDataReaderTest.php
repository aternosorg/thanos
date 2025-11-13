<?php

namespace Aternos\Thanos\Tests\Mca\Compression;

use Aternos\IO\Exception\IOException;
use Aternos\IO\System\File\TempMemoryFile;
use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\Exception\UnexpectedEndOfMcaFileException;
use Aternos\Thanos\Mca\Compression\RawMcaDataReader;

class RawMcaDataReaderTest extends ReaderTestCase
{
    public function testPassThroughData(): void
    {
        $file = $this->getDataFile("This is some test data.");
        $reader = new RawMcaDataReader($file, 0, $file->getSize(), 5);

        $data = "";
        $count = 0;
        foreach ($reader->read() as $dataChunk) {
            $data .= $dataChunk;
            $count ++;
        }

        $this->assertEquals("This is some test data.", $data);
        $this->assertEquals(5, $count);
    }

    public function testUnexpectedEof(): void
    {
        $file = $this->getDataFile("Short data");
        $reader = new RawMcaDataReader($file, 0, 50, 10);

        $this->expectExceptionMessage("Unexpected end of MCA file");
        $this->expectException(UnexpectedEndOfMcaFileException::class);

        foreach ($reader->read() as $dataChunk) {
            // Do nothing
        }
    }

    public function testEofCheckError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["isEndOfFile"]);
        $mock->expects($this->once())
            ->method("isEndOfFile")
            ->willThrowException(new IOException("IO error"));

        $reader = new RawMcaDataReader($mock, 0, 10, 5);

        $this->expectExceptionMessage("Could not check end of MCA file");
        $this->expectException(McaFileException::class);

        foreach ($reader->read() as $dataChunk) {
            // Do nothing
        }
    }

    public function testSetPositionError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["setPosition"]);
        $mock->expects($this->once())
            ->method("setPosition")
            ->willThrowException(new IOException("IO error"));

        $reader = new RawMcaDataReader($mock, 0, 10, 5);

        $this->expectExceptionMessage("Could not set position in MCA file");
        $this->expectException(McaFileException::class);

        foreach ($reader->read() as $dataChunk) {
            // Do nothing
        }
    }

    public function testReadError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["read"]);
        $mock->expects($this->once())
            ->method("read")
            ->willThrowException(new IOException("IO error"));

        $reader = new RawMcaDataReader($mock, 0, 10, 5);

        $this->expectExceptionMessage("Could not read data from MCA file");
        $this->expectException(McaFileException::class);

        foreach ($reader->read() as $dataChunk) {
            // Do nothing
        }
    }
}
