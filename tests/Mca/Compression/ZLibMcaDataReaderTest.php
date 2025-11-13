<?php

namespace Aternos\Thanos\Tests\Mca\Compression;

use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\Mca\Compression\ZLibMcaDataReader;
use PHPUnit\Framework\Attributes\TestWith;

class ZLibMcaDataReaderTest extends ReaderTestCase
{
    #[TestWith([ZLIB_ENCODING_DEFLATE])]
    #[TestWith([ZLIB_ENCODING_GZIP])]
    public function testUncompressData(int $encoding): void
    {
        $content = "This is some test data.";
        $compressedData = zlib_encode($content, $encoding);

        $file = $this->getDataFile($compressedData);
        $reader = new ZLibMcaDataReader($file, 0, $file->getSize(), 5, $encoding);

        $data = "";
        foreach ($reader->read() as $dataChunk) {
            $data .= $dataChunk;
        }

        $this->assertEquals($content, $data);
    }

    #[TestWith([ZLIB_ENCODING_DEFLATE])]
    #[TestWith([ZLIB_ENCODING_GZIP])]
    public function testInvalidCompressedData(int $encoding): void
    {
        $file = $this->getDataFile("Invalid compressed data");
        $reader = new ZLibMcaDataReader($file, 0, $file->getSize(), 5, $encoding);

        $this->expectExceptionMessage("Could not decompress zlib data: ");
        $this->expectException(McaFileException::class);

        foreach ($reader->read() as $dataChunk) {
            // Do nothing
        }
    }
}
