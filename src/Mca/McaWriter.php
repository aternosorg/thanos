<?php

namespace Aternos\Thanos\Mca;

use Aternos\IO\Exception\IOException;
use Aternos\IO\Interfaces\Features\CloseInterface;
use Aternos\IO\Interfaces\Features\GetNameInterface;
use Aternos\IO\Interfaces\Features\SetPositionInterface;
use Aternos\IO\Interfaces\Features\WriteInterface;
use Aternos\IO\System\File\File;
use Aternos\Thanos\Exception\McaExceptionInterface;
use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\Mca\Entry\McaEntryInterface;
use InvalidArgumentException;

class McaWriter
{
    /**
     * The Anvil location table stores the sector offset in 3 bytes and the sector count in 1 byte,
     * so a single chunk may span at most 255 sectors (0xFF * 4096 bytes) and the file may hold at
     * most 0xFFFFFF sectors. Exceeding either limit used to be masked away by the header packing,
     * producing a region file whose entries point at the wrong data.
     */
    protected const int MAX_SECTORS_PER_ENTRY = 0xFF;
    protected const int MAX_SECTOR_OFFSET = 0xFFFFFF;

    protected int $dataOffset = 8192;
    protected array $offsets;
    protected array $sizes;
    protected array $timestamps;
    protected bool $finalized = false;

    /**
     * @param string $filePath
     * @return static
     */
    public static function open(string $filePath): static
    {
        return new static(new File($filePath));
    }

    /**
     * @param WriteInterface&SetPositionInterface $output
     * @param bool $allowEntryOverwrite
     * @param bool $writeEmptyFile
     */
    public function __construct(
        protected WriteInterface&SetPositionInterface $output,
        protected bool $allowEntryOverwrite = false,
        bool $writeEmptyFile = false,
    )
    {
        $this->offsets = array_fill(0, 1024, 0);
        $this->sizes = array_fill(0, 1024, 0);
        $this->timestamps = array_fill(0, 1024, 0);
        $this->finalized = !$writeEmptyFile;
    }

    /**
     * @return string
     */
    protected function getFilename(): string
    {
        if ($this->output instanceof GetNameInterface) {
            try {
                return $this->output->getName();
            } catch (IOException) {
            }
        }
        return "Unknown MCA file";
    }

    /**
     * @param McaEntryInterface $entry
     * @return $this
     * @throws McaExceptionInterface
     */
    public function writeEntry(McaEntryInterface $entry): static
    {
        $this->finalized = false;

        $index = $entry->getRegionIndex();
        if ($index < 0 || $index >= 1024) {
            throw new InvalidArgumentException("Entry region index " . $index . " out of bounds in " . $this->getFilename());
        }

        if (!$this->allowEntryOverwrite && $this->sizes[$index] !== 0 && $this->offsets[$index] !== 0) {
            throw new InvalidArgumentException("Entry at index " . $index . " already exists in " . $this->getFilename());
        }

        $startOffset = $this->dataOffset;
        $written = 0;

        try {
            $this->output->setPosition($startOffset);
        } catch (IOException $e) {
            throw new McaFileException("Could not seek to entry position in output file in " . $this->getFilename(), previous: $e);
        }
        foreach ($entry->getSerializedData() as $dataChunk) {
            try {
                $this->output->write($dataChunk);
            } catch (IOException $e) {
                throw new McaFileException("Could not write entry data to output file " . $this->getFilename(), previous: $e);
            }
            $written += strlen($dataChunk);
        }

        $paddingLength = (4096 - ($written % 4096)) % 4096;
        if ($paddingLength > 0) {
            try {
                $this->output->write(str_repeat("\0", $paddingLength));
            } catch (IOException $e) {
                throw new McaFileException("Could not write padding to output file " . $this->getFilename(), previous: $e);
            }
            $written += $paddingLength;
        }

        $sizeSectors = intdiv($written, 4096);
        if ($sizeSectors > self::MAX_SECTORS_PER_ENTRY) {
            throw new McaFileException(sprintf(
                "Entry at index %d of %s needs %d sectors, but the location table can only store %d",
                $index,
                $this->getFilename(),
                $sizeSectors,
                self::MAX_SECTORS_PER_ENTRY
            ));
        }
        if (intdiv($this->dataOffset, 4096) > self::MAX_SECTOR_OFFSET) {
            throw new McaFileException(sprintf(
                "Region file %s would exceed the maximum addressable size (%d sectors)",
                $this->getFilename(),
                self::MAX_SECTOR_OFFSET
            ));
        }

        $this->dataOffset += $written;
        $this->offsets[$index] = $startOffset;
        $this->sizes[$index] = $this->dataOffset - $startOffset;
        $this->timestamps[$index] = $entry->getModifiedTime();

        return $this;
    }

    /**
     * @return $this
     * @throws McaFileException
     */
    public function finalize(): static
    {
        if ($this->finalized) {
            return $this;
        }

        $locationTable = [];
        for ($i = 0; $i < 1024; $i++) {
            $offset = intdiv($this->offsets[$i], 4096);
            $size = intdiv($this->sizes[$i], 4096);
            $locationTable[] = ($offset << 8) | $size;
        }

        $locationData = pack('N1024', ...$locationTable);
        $timestampData = pack('N1024', ...$this->timestamps);

        try {
            $this->output->setPosition(0);
        } catch (IOException $e) {
            throw new McaFileException("Could not seek to start position in output file " . $this->getFilename(), previous: $e);
        }
        try {
            $this->output->write($locationData);
            $this->output->write($timestampData);
        } catch (IOException $e) {
            throw new McaFileException("Could not write MCA header to output file " . $this->getFilename(), previous: $e);
        }

        $this->finalized = true;

        return $this;
    }

    /**
     * @return $this
     * @throws McaFileException
     */
    public function close(): static
    {
        if (!$this->finalized) {
            $this->finalize();
        }
        if ($this->output instanceof CloseInterface) {
            try {
                $this->output->close();
            } catch (IOException $e) {
                throw new McaFileException("Could not close output file " . $this->getFilename(), previous: $e);
            }
        }
        return $this;
    }

    /**
     * @param bool $allowEntryOverwrite
     * @return $this
     */
    public function setAllowEntryOverwrite(bool $allowEntryOverwrite): static
    {
        $this->allowEntryOverwrite = $allowEntryOverwrite;
        return $this;
    }
}
