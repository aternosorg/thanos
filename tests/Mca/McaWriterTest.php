<?php

namespace Aternos\Thanos\Tests\Mca;

use Aternos\IO\Exception\IOException;
use Aternos\IO\System\File\File;
use Aternos\IO\System\File\TempMemoryFile;
use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\Mca\Entry\McaEntry;
use Aternos\Thanos\Mca\McaReader;
use Aternos\Thanos\Mca\McaWriter;
use Aternos\Thanos\Tests\ThanosTestCase;
use InvalidArgumentException;
use ReflectionClass;

class McaWriterTest extends ThanosTestCase
{
    public function testWriteEntry(): void
    {
        $rawEntry = $this->makeEntry("test");
        $data = $this->getDataFile($rawEntry);
        $entry = new McaEntry($data, 0, 4096, 123, 45678);

        $output = new TempMemoryFile();
        $writer = new McaWriter($output);
        $writer->writeEntry($entry);
        $writer->finalize();

        $reader = new McaReader($output, 0, 0);
        /** @var McaEntry[] $entries */
        $entries = iterator_to_array($reader->getChunks());
        $this->assertCount(1, $entries);
        $readEntry = $entries[0];
        $this->assertEquals(123, $readEntry->getRegionIndex());
        $this->assertEquals(45678, $readEntry->getModifiedTime());

        $content = "";
        foreach ($readEntry->getData() as $dataChunk) {
            $content .= $dataChunk;
        }
        $this->assertEquals("test", $content);

        $rawReadEntry = "";
        foreach ($readEntry->getSerializedData() as $dataChunk) {
            $rawReadEntry .= $dataChunk;
        }
        $this->assertEquals($rawEntry, $rawReadEntry);
    }

    public function testOutOfBoundsIndex(): void
    {
        $rawEntry = $this->makeEntry("test");
        $data = $this->getDataFile($rawEntry);
        $entry = new McaEntry($data, 0, 4096, 2000, 45678);

        $output = new TempMemoryFile();
        $writer = new McaWriter($output);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Entry region index out of bounds: 2000");
        $writer->writeEntry($entry);
    }

    public function testDisallowOverrides(): void
    {
        $rawEntry = $this->makeEntry("test");
        $data = $this->getDataFile($rawEntry);
        $entry = new McaEntry($data, 0, 4096, 123, 45678);

        $output = new TempMemoryFile();
        $writer = new McaWriter($output);
        $writer->writeEntry($entry);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Entry at index 123 already exists in MCA file");
        $writer->writeEntry($entry);
    }

    public function testAllowOverrides(): void
    {
        $rawEntry = $this->makeEntry("test");
        $data = $this->getDataFile($rawEntry);
        $entry = new McaEntry($data, 0, 4096, 123, 45678);

        $output = new TempMemoryFile();
        $writer = new McaWriter($output, allowEntryOverwrite: true);
        $writer->writeEntry($entry);
        // Should not throw exception
        $writer->writeEntry($entry);
        $this->assertTrue(true);
    }

    public function testSetAllowOverride(): void
    {
        $rawEntry = $this->makeEntry("test");
        $data = $this->getDataFile($rawEntry);
        $entry = new McaEntry($data, 0, 4096, 123, 45678);

        $output = new TempMemoryFile();
        $writer = new McaWriter($output);
        $writer->setAllowEntryOverwrite(true);
        $writer->writeEntry($entry);
        // Should not throw exception
        $writer->writeEntry($entry);
        $this->assertTrue(true);
    }

    public function testSetPositionError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["setPosition"]);
        $mock->expects($this->once())
            ->method("setPosition")
            ->willThrowException(new IOException("IO error"));

        $rawEntry = $this->makeEntry("test");
        $data = $this->getDataFile($rawEntry);
        $entry = new McaEntry($data, 0, 4096, 123, 45678);

        $writer = new McaWriter($mock);
        $this->expectExceptionMessage("Could not seek to entry position in output file");
        $this->expectException(McaFileException::class);
        $writer->writeEntry($entry);
    }

    public function testWriteContentError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["write"]);
        $mock->expects($this->once())
            ->method("write")
            ->willThrowException(new IOException("IO error"));

        $rawEntry = $this->makeEntry("test");
        $data = $this->getDataFile($rawEntry);
        $entry = new McaEntry($data, 0, 4096, 123, 45678);

        $writer = new McaWriter($mock);
        $this->expectExceptionMessage("Could not write entry data to output file");
        $this->expectException(McaFileException::class);
        $writer->writeEntry($entry);
    }

    public function testWritePaddingError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["write"]);
        $mock->expects($this->exactly(2))
            ->method("write")
            ->willReturnOnConsecutiveCalls(
                $mock,
                $this->throwException(new IOException("IO error"))
            );

        $rawEntry = $this->makeEntry("test");
        $data = $this->getDataFile($rawEntry);
        $entry = new McaEntry($data, 0, 4096, 123, 45678);

        $writer = new McaWriter($mock);
        $this->expectExceptionMessage("Could not write padding to output file");
        $this->expectException(McaFileException::class);
        $writer->writeEntry($entry);
    }

    public function testDoNotUpdateTablesAndOffsetOnError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["write"]);
        $mock->expects($this->exactly(2))
            ->method("write")
            ->willReturnOnConsecutiveCalls(
                $mock,
                $this->throwException(new IOException("IO error"))
            );

        $rawEntry = $this->makeEntry("test");
        $data = $this->getDataFile($rawEntry);
        $entry = new McaEntry($data, 0, 4096, 123, 45678);

        $writer = new McaWriter($mock);
        try {
            $writer->writeEntry($entry);
        } catch (McaFileException) {
            // Ignore
        }

        $reflection = new ReflectionClass($writer);
        $offsets = $reflection->getProperty("offsets")->getValue($writer);
        $sizes = $reflection->getProperty("sizes")->getValue($writer);
        $timestamps = $reflection->getProperty("timestamps")->getValue($writer);
        $dataOffset = $reflection->getProperty("dataOffset")->getValue($writer);

        $this->assertEquals(0, $offsets[123]);
        $this->assertEquals(0, $sizes[123]);
        $this->assertEquals(0, $timestamps[123]);
        $this->assertEquals(8192, $dataOffset); // Initial offset after tables
    }

    public function testFinalizeSetPositionError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["setPosition"]);
        $mock->expects($this->once())
            ->method("setPosition")
            ->willThrowException(new IOException("IO error"));

        $writer = new McaWriter($mock, writeEmptyFile: true);
        $this->expectExceptionMessage("Could not seek to entry position in output file");
        $this->expectException(McaFileException::class);
        $writer->finalize();
    }

    public function testSkipFinalizeIfAlreadyFinalized(): void
    {
        $file = new TempMemoryFile();
        $writer = new McaWriter($file, writeEmptyFile: false);
        $writer->finalize();

        $this->assertEquals(0, $file->getPosition());
        $this->assertEquals(0, $file->getSize());
    }

    public function testFinalizeWriteError(): void
    {
        $mock = $this->createPartialMock(TempMemoryFile::class, ["write"]);
        $mock->expects($this->once())
            ->method("write")
            ->willThrowException(new IOException("IO error"));

        $writer = new McaWriter($mock, writeEmptyFile: true);
        $this->expectExceptionMessage("Could not write MCA header to output file");
        $this->expectException(McaFileException::class);
        $writer->finalize();
    }

    public function testCloseClosesResourceAndFinalizes(): void
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

        $rawEntry = $this->makeEntry("test");
        $data = $this->getDataFile($rawEntry);
        $entry = new McaEntry($data, 0, 4096, 123, 45678);

        $writer = new McaWriter($file);
        $writer->writeEntry($entry);
        $reflection = new ReflectionClass($writer);
        $this->assertFalse($reflection->getProperty("finalized")->getValue($writer));
        $writer->close();
        $this->assertTrue($reflection->getProperty("finalized")->getValue($writer));
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
        $reader = new McaWriter($file);

        $this->expectException(McaFileException::class);
        $this->expectExceptionMessage("Could not close output file");
        $reader->close();
    }

    public function testOpen(): void
    {
        $writer = McaWriter::open(static::TEST_DATA . "/test.mca");
        $reflection = new ReflectionClass($writer);
        $output = $reflection->getProperty("output")->getValue($writer);
        $this->assertInstanceOf(File::class, $output);
        $this->assertEquals(static::TEST_DATA . "/test.mca", $output->getPath());
    }
}
