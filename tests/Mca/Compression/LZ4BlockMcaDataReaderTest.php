<?php

namespace Aternos\Thanos\Tests\Mca\Compression;

use Aternos\IO\Exception\IOException;
use Aternos\IO\System\File\TempMemoryFile;
use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\Exception\UnexpectedEndOfMcaFileException;
use Aternos\Thanos\Mca\Compression\LZ4\LZ4CompressionMethod;
use Aternos\Thanos\Mca\Compression\LZ4BlockMcaDataReader;

class LZ4BlockMcaDataReaderTest extends LZ4TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists("lz4_uncompress")) {
            $this->markTestSkipped("LZ4 extension is not installed");
        }
    }

    public function testDecompressData(): void
    {
        $block1 = $this->makeLZ4Block("block1");
        $block2 = $this->makeLZ4Block("block2", LZ4CompressionMethod::RAW);

        $file = $this->getDataFile($block1 . $block2);
        $reader = new LZ4BlockMcaDataReader($file, 0, $file->getSize());
        $data = "";
        foreach ($reader->read() as $dataChunk) {
            $data .= $dataChunk;
        }

        $this->assertEquals("block1block2", $data);
    }

    public function testUnexpectedEof(): void
    {
        $file = $this->getDataFile("test data");
        $file->read(64);
        $reader = new LZ4BlockMcaDataReader($file, 0, $file->getSize());

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

        $reader = new LZ4BlockMcaDataReader($mock, 0, 10);

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

        $reader = new LZ4BlockMcaDataReader($mock, 0, 10);

        $this->expectExceptionMessage("Could not set position in MCA file");
        $this->expectException(McaFileException::class);

        foreach ($reader->read() as $dataChunk) {
            // Do nothing
        }
    }

    public function testReadHeaderError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["read"]);
        $mock->expects($this->once())
            ->method("read")
            ->willThrowException(new IOException("IO error"));

        $reader = new LZ4BlockMcaDataReader($mock, 0, 10);

        $this->expectExceptionMessage("Could not read LZ4 block header data from MCA file");
        $this->expectException(McaFileException::class);

        foreach ($reader->read() as $dataChunk) {
            // Do nothing
        }
    }

    public function testReadBodyError(): void
    {
        $file = new class extends TempMemoryFile
        {
            protected int $readCount = 0;

            public function read(int $length): string
            {
                $this->readCount++;
                if ($this->readCount === 2) {
                    throw new IOException("IO error");
                }
                return parent::read($length);
            }
        };

        $file = $this->getDataFile($this->makeLZ4Block("test"), $file);

        $reader = new LZ4BlockMcaDataReader($file, 0, 10);

        $this->expectExceptionMessage("Could not read LZ4 block data from MCA file");
        $this->expectException(McaFileException::class);

        foreach ($reader->read() as $dataChunk) {
            // Do nothing
        }
    }

    public function testChecksumMismatch(): void
    {
        $block = $this->makeLZ4Block("test", headerOverrides: [
            "checksum" => 0x12345678,
        ]);

        $file = $this->getDataFile($block);
        $reader = new LZ4BlockMcaDataReader($file, 0, $file->getSize());
        $this->expectExceptionMessage("LZ4 block checksum mismatch");
        $this->expectException(McaFileException::class);

        foreach ($reader->read() as $dataChunk) {
            // Do nothing
        }
    }

    public function testInvalidBlockHeader(): void
    {
        $file = $this->getDataFile("abcdefghijklmnopqrstuvwxyz");
        $reader = new LZ4BlockMcaDataReader($file, 0, $file->getSize());
        $this->expectExceptionMessage("Could not parse LZ4 block header");
        $this->expectException(McaFileException::class);

        foreach ($reader->read() as $dataChunk) {
            // Do nothing
        }
    }

    public function testInvalidCompressedData(): void
    {
        $header = $this->makeLZ4BlockHeader(
            LZ4CompressionMethod::LZ4,
            11,
            10,
            10,
            12345
        );

        $file = $this->getDataFile($header . "invaliddat");
        $reader = new LZ4BlockMcaDataReader($file, 0, $file->getSize());
        $this->expectExceptionMessage("Could not uncompress LZ4 block data:");
        $this->expectException(McaFileException::class);

        foreach ($reader->read() as $dataChunk) {
            // Do nothing
        }
    }
}
