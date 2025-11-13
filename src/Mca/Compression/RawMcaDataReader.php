<?php

namespace Aternos\Thanos\Mca\Compression;

use Aternos\IO\Exception\IOException;
use Aternos\IO\Interfaces\Features\IsEndOfFileInterface;
use Aternos\IO\Interfaces\Features\ReadInterface;
use Aternos\IO\Interfaces\Features\SetPositionInterface;
use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\Exception\UnexpectedEndOfMcaFileException;
use Generator;

class RawMcaDataReader extends AbstractMcaDataReader
{
    /**
     * @param ReadInterface&IsEndOfFileInterface&SetPositionInterface $input
     * @param int $startOffset
     * @param int $length
     * @param int $chunkSize
     */
    public function __construct(
        ReadInterface&SetPositionInterface&IsEndOfFileInterface $input,
        int                                                     $startOffset,
        int                                                     $length,
        protected int                                           $chunkSize,
    )
    {
        parent::__construct($input, $startOffset, $length);
    }

    /**
     * @inheritDoc
     */
    public function read(): Generator
    {
        $offset = $this->startOffset;
        while ($offset < $this->startOffset + $this->length) {
            try {
                $eof = $this->input->isEndOfFile();
            } catch (IOException $e) {
                throw new McaFileException("Could not check end of MCA file", previous: $e);
            }
            if ($eof) {
                throw new UnexpectedEndOfMcaFileException("Unexpected end of MCA file");
            }

            try {
                $this->input->setPosition($offset);
            } catch (IOException $e) {
                throw new McaFileException("Could not set position in MCA file", previous: $e);
            }
            $toRead = min($this->chunkSize, $this->startOffset + $this->length - $offset);
            try {
                $data = $this->input->read($toRead);
            } catch (IOException $e) {
                throw new McaFileException("Could not read data from MCA file", previous: $e);
            }

            yield $data;
            $offset += strlen($data);
        }
    }
}
