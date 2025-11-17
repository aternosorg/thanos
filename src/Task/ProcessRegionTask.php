<?php

namespace Aternos\Thanos\Task;

use Aternos\Taskmaster\Task\OnChild;
use Aternos\Thanos\Mca\McaReader;
use Aternos\Thanos\Mca\McaWriter;
use Aternos\Thanos\PathPair;
use Aternos\Thanos\Pattern\ChunkPatternInterface;
use Aternos\Thanos\World\Chunk;

class ProcessRegionTask extends ThanosTask
{
    /**
     * @param PathPair $chunkRegion
     * @param PathPair|null $entityRegion
     * @param PathPair|null $poiRegion
     * @param ChunkPatternInterface[] $patterns
     */
    public function __construct(
        protected PathPair $chunkRegion,
        protected ?PathPair $entityRegion,
        protected ?PathPair $poiRegion,
        protected array $patterns = [],
    )
    {
    }

    /**
     * @inheritDoc
     */
    #[OnChild]
    public function run(): int
    {
        $chunkReader = McaReader::open($this->chunkRegion->getSource());
        $chunkWriter = McaWriter::open($this->chunkRegion->getDestination());

        if ($this->entityRegion) {
            $entityReader = McaReader::open($this->entityRegion->getSource());
            $entityWriter = McaWriter::open($this->entityRegion->getDestination());
        } else {
            $entityReader = null;
            $entityWriter = null;
        }

        if ($this->poiRegion) {
            $poiReader = McaReader::open($this->poiRegion->getSource());
            $poiWriter = McaWriter::open($this->poiRegion->getDestination());
        } else {
            $poiReader = null;
            $poiWriter = null;
        }

        $removedChunks = 0;
        foreach ($chunkReader->getChunks() as $chunkEntry) {
            $entitiesEntry = $entityReader?->getChunk($chunkEntry->getRegionIndex());
            $poiEntry = $poiReader?->getChunk($chunkEntry->getRegionIndex());

            $chunk = new Chunk(
                $chunkEntry,
                $entitiesEntry,
                $poiEntry,
                $chunkReader->getXPosition(),
                $chunkReader->getZPosition()
            );

            $keep = false;
            foreach ($this->patterns as $pattern) {
                if ($pattern->matches($chunk)) {
                    $keep = true;
                    break;
                }
            }

            if ($keep) {
                $chunkWriter->writeEntry($chunkEntry);
                if ($entitiesEntry !== null) {
                    $entityWriter->writeEntry($entitiesEntry);
                }
                if ($poiEntry !== null) {
                    $poiWriter->writeEntry($poiEntry);
                }
            } else {
                $removedChunks++;
            }
        }

        $chunkReader->close();
        $chunkWriter->close();
        $entityReader?->close();
        $entityWriter?->close();
        $poiReader?->close();
        $poiWriter?->close();

        return $removedChunks;
    }
}
