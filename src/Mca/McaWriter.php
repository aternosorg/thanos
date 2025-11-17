<?php

namespace Aternos\Thanos\Mca;

use Aternos\IO\Exception\IOException;
use Aternos\IO\Interfaces\Features\CloseInterface;
use Aternos\IO\Interfaces\Features\SetPositionInterface;
use Aternos\IO\Interfaces\Features\WriteInterface;
use Aternos\IO\System\File\File;
use Aternos\Thanos\Exception\McaExceptionInterface;
use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\Mca\Entry\McaEntryInterface;
use InvalidArgumentException;

class McaWriter
{
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
     * @param McaEntryInterface $entry
     * @return $this
     * @throws McaExceptionInterface
     */
    public function writeEntry(McaEntryInterface $entry): static
    {
        $this->finalized = false;

        $index = $entry->getRegionIndex();
        if ($index < 0 || $index >= 1024) {
            throw new InvalidArgumentException("Entry region index out of bounds: " . $index);
        }

        if (!$this->allowEntryOverwrite && $this->sizes[$index] !== 0 && $this->offsets[$index] !== 0) {
            throw new InvalidArgumentException("Entry at index " . $index . " already exists in MCA file");
        }

        $startOffset = $this->dataOffset;
        $written = 0;

        try {
            $this->output->setPosition($startOffset);
        } catch (IOException $e) {
            throw new McaFileException("Could not seek to entry position in output file", previous: $e);
        }
        foreach ($entry->getSerializedData() as $dataChunk) {
            try {
                $this->output->write($dataChunk);
            } catch (IOException $e) {
                throw new McaFileException("Could not write entry data to output file", previous: $e);
            }
            $written += strlen($dataChunk);
        }

        $paddingLength = (4096 - ($written % 4096)) % 4096;
        if ($paddingLength > 0) {
            try {
                $this->output->write(str_repeat("\0", $paddingLength));
            } catch (IOException $e) {
                throw new McaFileException("Could not write padding to output file", previous: $e);
            }
            $written += $paddingLength;
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
            throw new McaFileException("Could not seek to entry position in output file", previous: $e);
        }
        try {
            $this->output->write($locationData);
            $this->output->write($timestampData);
        } catch (IOException $e) {
            throw new McaFileException("Could not write MCA header to output file", previous: $e);
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
                throw new McaFileException("Could not close output file", previous: $e);
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
