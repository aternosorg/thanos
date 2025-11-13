<?php

namespace Aternos\Thanos\Tests\Mca;

use Aternos\IO\Exception\IOException;
use Aternos\IO\System\File\TempMemoryFile;
use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\Mca\McaReader;
use Aternos\Thanos\Tests\ThanosTestCase;

class McaReaderTest extends ThanosTestCase
{
    const string TEST_REGION = __DIR__ . "/Fixtures/r.0.0.mca";

    public function testOpenFile(): void
    {
        $reader = McaReader::open(static::TEST_REGION);
        $this->assertEquals(0, $reader->getXPosition());
        $this->assertEquals(0, $reader->getZPosition());

        $chunks = iterator_to_array($reader->getChunks());
        $this->assertCount(676, $chunks);

        $this->assertNull($reader->getChunk(123));

        $chunk = $reader->getChunk(738);
        $this->assertNotNull($chunk);
        $this->assertEquals(738, $chunk->getRegionIndex());
        $this->assertEquals(2, $chunk->getXPos());
        $this->assertEquals(23, $chunk->getZPos());

        $chunk = $reader->getChunkAt(2, 23);
        $this->assertNotNull($chunk);
        $this->assertEquals(738, $chunk->getRegionIndex());
        $this->assertEquals(2, $chunk->getXPos());
        $this->assertEquals(23, $chunk->getZPos());

        $reader->close();
    }

    public function testCloseClosesResource(): void
    {
        $file = new class extends TempMemoryFile {
            public bool $closed = false;

            /** @noinspection PhpHierarchyChecksInspection */
            public function close(): static
            {
                $this->closed = true;
                return parent::close();
            }
        };
        $reader = new McaReader($file, 0, 0);
        $reader->close();
        $this->assertTrue($file->closed);
    }

    public function testCloseError(): void
    {
        $file = new class extends TempMemoryFile {
            public bool $closed = false;

            /** @noinspection PhpHierarchyChecksInspection */
            public function close(): static
            {
                if (!$this->closed) {
                    $this->closed = true;
                    throw new IOException("IO error");
                }
                return parent::close();
            }
        };
        $reader = new McaReader($file, 0, 0);

        $this->expectException(McaFileException::class);
        $this->expectExceptionMessage("Could not close MCA file");
        $reader->close();
    }

    public function testHeaderReadError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["read"]);
        $mock->expects($this->once())
            ->method("read")
            ->willThrowException(new IOException("IO error"));

        $reader = new McaReader($mock, 0, 0);

        $this->expectExceptionMessage("Could not read MCA file header");
        $this->expectException(McaFileException::class);
        $reader->getChunks()->current();
    }

    public function testOpenMalformedFilename(): void
    {
        $this->expectExceptionMessage("Unable to get region position from file name");
        $this->expectException(McaFileException::class);
        McaReader::open(__DIR__ . "/Fixtures/invalid_name.mca");
    }

    public function testReadInvalidChunkTable(): void
    {
        $data = str_repeat("\x00", 4095 - 1); // One byte short
        $file = $this->getDataFile($data);
        $reader = new McaReader($file, 0, 0);

        $this->expectExceptionMessage("Failed to decode chunk table");
        $this->expectException(McaFileException::class);
        $reader->getChunks()->current();
    }

    public function testReadInvalidTimestampTable(): void
    {
        $data = str_repeat("\x00", 8192 - 1); // One byte short
        $file = $this->getDataFile($data);
        $reader = new McaReader($file, 0, 0);

        $this->expectExceptionMessage("Failed to decode timestamp table");
        $this->expectException(McaFileException::class);
        $reader->getChunks()->current();
    }
}
