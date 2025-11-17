<?php

namespace Aternos\Thanos\Mca;

use Aternos\IO\Exception\IOException;
use Aternos\IO\Interfaces\Features\CloseInterface;
use Aternos\IO\Interfaces\Features\IsEndOfFileInterface;
use Aternos\IO\Interfaces\Features\ReadInterface;
use Aternos\IO\Interfaces\Features\SetPositionInterface;
use Aternos\IO\System\File\File;
use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\Mca\Entry\McaEntry;
use Generator;

class McaReader
{
    public const string MCA_FILE_PATTERN = "#r\.(-?\d+)\.(-?\d+)\.mca$#";

    protected ?array $offsets = null;
    protected ?array $sizes = null;
    protected ?array $timestamps = null;

    /**
     * @param string $filePath
     * @return static
     * @throws McaFileException
     */
    public static function open(string $filePath): static
    {
        preg_match(static::MCA_FILE_PATTERN, $filePath, $matches);
        if (!isset($matches[1]) || !isset($matches[2])) {
            throw new McaFileException("Unable to get region position from file name");
        }

        $file = new File($filePath);
        return new static($file, intval($matches[1]), intval($matches[2]));
    }

    /**
     * @param ReadInterface&IsEndOfFileInterface&SetPositionInterface $input
     * @param int $xPosition
     * @param int $zPosition
     */
    public function __construct(
        protected ReadInterface&SetPositionInterface&IsEndOfFileInterface $input,
        protected int                                                     $xPosition,
        protected int                                                     $zPosition,
    )
    {
    }

    /**
     * @return int
     */
    public function getXPosition(): int
    {
        return $this->xPosition;
    }

    /**
     * @return int
     */
    public function getZPosition(): int
    {
        return $this->zPosition;
    }

    /**
     * @return void
     * @throws IOException
     * @throws McaFileException
     */
    protected function readHeader(): void
    {
        $this->input->setPosition(0);
        $chunkData = $this->input->read(4 * 1024);
        $values = @unpack('N1024', $chunkData);
        if ($values === false) {
            throw new McaFileException("Failed to decode chunk table");
        }

        $offsets = [];
        $sizes = [];
        foreach ($values as $val) {
            $offset = ($val >> 8) * 4096;
            $size = ($val & 0xFF) * 4096;
            $offsets[] = $offset;
            $sizes[] = $size;
        }

        $timeData = $this->input->read(4 * 1024);
        $values = @unpack('N1024', $timeData);
        if ($values === false || count($values) !== 1024) {
            throw new McaFileException("Failed to decode timestamp table");
        }

        $this->offsets = $offsets;
        $this->sizes = $sizes;
        $this->timestamps = array_values($values);
    }

    /**
     * @return $this
     * @throws McaFileException
     */
    protected function ensureHeaderIsRead(): static
    {
        if ($this->offsets !== null && $this->sizes !== null && $this->timestamps !== null) {
            return $this;
        }

        try {
            $this->readHeader();
        } catch (IOException $e) {
            throw new McaFileException("Could not read MCA file header", previous: $e);
        }
        return $this;
    }

    /**
     * @return Generator<McaEntry>
     * @throws McaFileException
     */
    public function getChunks(): Generator
    {
        $this->ensureHeaderIsRead();

        for ($i = 0; $i < 1024; $i++) {
            $offset = $this->offsets[$i];
            $size = $this->sizes[$i];
            $timestamp = $this->timestamps[$i];
            if ($offset === 0 || $size === 0) {
                continue;
            }
            yield new McaEntry(
                $this->input,
                $offset,
                $size,
                $i,
                $timestamp
            );
        }
    }

    /**
     * @param int $index
     * @return McaEntry|null
     * @throws McaFileException
     */
    public function getChunk(int $index): ?McaEntry
    {
        $this->ensureHeaderIsRead();

        $offset = $this->offsets[$index];
        $size = $this->sizes[$index];
        $timestamp = $this->timestamps[$index];
        if ($offset === 0 || $size === 0) {
            return null;
        }
        return new McaEntry(
            $this->input,
            $offset,
            $size,
            $index,
            $timestamp
        );
    }

    /**
     * @param int $x
     * @param int $z
     * @return McaEntry|null
     * @throws McaFileException
     */
    public function getChunkAt(int $x, int $z): ?McaEntry
    {
        $index = ($x % 32) + ($z % 32) * 32;
        return $this->getChunk($index);
    }

    /**
     * @return $this
     * @throws McaFileException
     */
    public function close(): static
    {
        if ($this->input instanceof CloseInterface) {
            try {
                $this->input->close();
            } catch (IOException $e) {
                throw new McaFileException("Could not close MCA file", previous: $e);
            }
        }
        return $this;
    }
}
